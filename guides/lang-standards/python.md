# Python — ECPay 整合程式規範

> 本檔為 AI 生成 ECPay 整合程式碼時的 Python 專屬規範。
> 加密函式：[guides/13 §Python](../13-checkmacvalue.md) + [guides/14 §Python](../14-aes-encryption.md)
> E2E 範例：[guides/00 §Quick Start](../00-getting-started.md) + [guides/23](../23-multi-language-integration.md)

## 版本與環境

- **最低版本**：Python 3.9+（`dict` 保證插入順序自 3.7，`|` 合併 dict 自 3.9）
- **推薦版本**：Python 3.11+（更佳錯誤訊息、tomllib 內建）
- **套件管理**：`pip`（requirements.txt）或 `poetry`（pyproject.toml）

## 推薦依賴

```txt
# requirements.txt
pycryptodome>=3.20    # AES 加解密
requests>=2.31        # HTTP Client（同步）
python-dotenv>=1.0    # 環境變數載入
```

> **httpx vs requests**：若專案已使用 async，改用 `httpx`（支援 async/sync 雙模式）。純同步專案用 `requests` 即可。

## 命名慣例

```python
# 函式 / 變數：snake_case
def generate_check_mac_value(params, hash_key, hash_iv): ...
merchant_trade_no = f"ORDER{int(time.time())}"

# 類別：PascalCase
class EcpayPaymentClient: ...

# 常數：UPPER_SNAKE_CASE
ECPAY_PAYMENT_URL = "https://payment.ecpay.com.tw/Cashier/AioCheckOut/V5"

# 檔案：snake_case.py
# ecpay_payment.py, ecpay_aes.py, ecpay_callback.py
```

## 型別定義

```python
from typing import TypedDict, Literal

class AioParams(TypedDict, total=False):
    MerchantID: str
    MerchantTradeNo: str
    MerchantTradeDate: str  # yyyy/MM/dd HH:mm:ss
    PaymentType: Literal["aio"]
    TotalAmount: str        # ⚠️ 整數字串，非 int
    TradeDesc: str
    ItemName: str
    ReturnURL: str
    ChoosePayment: str
    EncryptType: Literal["1"]
    CheckMacValue: str

class AesRequest(TypedDict):
    MerchantID: str
    RqHeader: dict          # {"Timestamp": int, "Revision": "3.0.0"}  ← Revision 依服務：B2C 發票="3.0.0", B2B/票證="1.0.0", 站內付 2.0=省略（不含此 key）
    Data: str               # AES 加密後 Base64 字串

class AesResponse(TypedDict):
    TransCode: int          # 外層傳輸層狀態
    TransMsg: str
    Data: str               # AES 加密的業務資料

class CallbackParams(TypedDict, total=False):
    MerchantID: str
    MerchantTradeNo: str
    RtnCode: str            # ⚠️ 型別依協議：CMV 服務（AIO/物流）→ 字串 "1"；AES-JSON 服務（ECPG/發票）解密後 → 整數 1（此 TypedDict 示範 CMV 服務）
    RtnMsg: str
    TradeNo: str
    TradeAmt: str
    PaymentDate: str
    PaymentType: str
    CheckMacValue: str
    SimulatePaid: str
```

## 錯誤處理

```python
import requests
from requests.exceptions import Timeout, ConnectionError

class EcpayApiError(Exception):
    """ECPay API 業務層錯誤"""
    def __init__(self, trans_code: int, rtn_code, rtn_msg: str):
        self.trans_code = trans_code
        self.rtn_code = rtn_code
        self.rtn_msg = rtn_msg
        super().__init__(f"TransCode={trans_code}, RtnCode={rtn_code}: {rtn_msg}")

def call_aes_api(url: str, request_body: dict, hash_key: str, hash_iv: str) -> dict:
    """AES-JSON API 呼叫模板（含雙層錯誤檢查）"""
    try:
        resp = requests.post(url, json=request_body, timeout=30)
        resp.raise_for_status()
    except Timeout:
        raise EcpayApiError(-1, None, "請求逾時，請稍後重試")
    except ConnectionError:
        raise EcpayApiError(-1, None, "連線失敗，請檢查網路或 domain")

    result = resp.json()

    # 第一層：TransCode（傳輸層）
    if result.get("TransCode") != 1:
        raise EcpayApiError(result["TransCode"], None, result.get("TransMsg", ""))

    # 第二層：解密 Data → 檢查 RtnCode（業務層）
    data = aes_decrypt(result["Data"], hash_key, hash_iv)
    rtn_code = data.get("RtnCode")
    if rtn_code != 1:  # AES-JSON 服務：RtnCode 為整數 1
        raise EcpayApiError(1, rtn_code, data.get("RtnMsg", ""))

    return data
```

## HTTP Client 設定

```python
import requests

session = requests.Session()
session.headers.update({
    "User-Agent": "ECPay-Integration/1.0",
    "Accept": "application/json",
})
# 超時建議 30 秒（ECPay 部分 API 處理較慢）
# 403 = Rate Limit，需等待約 30 分鐘

# Retry 策略（建議用於 transient errors）
from requests.adapters import HTTPAdapter
from urllib3.util.retry import Retry

retry_strategy = Retry(total=3, backoff_factor=1, status_forcelist=[500, 502, 503])
adapter = HTTPAdapter(max_retries=retry_strategy)
session.mount("https://", adapter)
# ⚠️ 403 (Rate Limit) 不可自動重試 — 需等待約 30 分鐘
```

## Callback Handler 模板

```python
# Flask 範例
from flask import Flask, request, Response
import hmac

app = Flask(__name__)

@app.post("/ecpay/callback")
def ecpay_callback():
    params = dict(request.form)

    # 1. 驗證 CheckMacValue（timing-safe）
    received_cmv = params.pop("CheckMacValue", "")
    expected_cmv = generate_check_mac_value(params, HASH_KEY, HASH_IV)
    if not hmac.compare_digest(received_cmv, expected_cmv):
        return Response("CheckMacValue Error", status=400)

    # 2. 冪等性：檢查訂單是否已處理
    trade_no = params["MerchantTradeNo"]
    if is_order_already_processed(trade_no):
        return Response("1|OK", status=200, content_type="text/plain")

    # 3. 處理業務邏輯（RtnCode 是字串）
    if params.get("RtnCode") == "1":
        process_payment_success(params)

    # 4. 必須回傳 HTTP 200 + "1|OK"
    return Response("1|OK", status=200, content_type="text/plain")
```

> ⚠️ ECPay Callback URL 僅支援 port 80 (HTTP) / 443 (HTTPS)，開發環境使用 ngrok 轉發到本機任意 port。

## 日期與時區

```python
from datetime import datetime, timezone, timedelta

# ⚠️ ECPay 所有時間欄位皆為台灣時間（UTC+8）
TW_TZ = timezone(timedelta(hours=8))

# MerchantTradeDate 格式：yyyy/MM/dd HH:mm:ss（非 ISO 8601）
merchant_trade_date = datetime.now(TW_TZ).strftime('%Y/%m/%d %H:%M:%S')
# → "2026/03/11 12:10:41"

# AES RqHeader.Timestamp：Unix 秒數（非毫秒）
import time
timestamp = int(time.time())
# ⚠️ time.time() 回傳 float，需 int() 截斷
```

## 環境變數

```python
# .env（不可提交至版控）
ECPAY_MERCHANT_ID=3002607
ECPAY_HASH_KEY=pwFHCqoQZGmho4w6
ECPAY_HASH_IV=EkRm7iFT261dpevs
ECPAY_ENV=stage  # stage / production

# config.py
import os
from dotenv import load_dotenv

load_dotenv()

MERCHANT_ID = os.environ["ECPAY_MERCHANT_ID"]
HASH_KEY = os.environ["ECPAY_HASH_KEY"]
HASH_IV = os.environ["ECPAY_HASH_IV"]

BASE_URL = (
    "https://payment-stage.ecpay.com.tw"
    if os.getenv("ECPAY_ENV") == "stage"
    else "https://payment.ecpay.com.tw"
)

# ⚠️ 啟動時驗證必要環境變數
for var in ("ECPAY_MERCHANT_ID", "ECPAY_HASH_KEY", "ECPAY_HASH_IV"):
    if not os.environ.get(var):
        raise RuntimeError(f"Missing required environment variable: {var}")
```

## 日誌與監控

```python
import logging

logger = logging.getLogger("ecpay")

# ⚠️ 絕不記錄 HashKey / HashIV / CheckMacValue
# ✅ 記錄：API 呼叫結果、交易編號、錯誤訊息
logger.info("ECPay API 呼叫成功: MerchantTradeNo=%s", merchant_trade_no)
logger.error("ECPay API 錯誤: TransCode=%d, RtnCode=%s", trans_code, rtn_code)

# 推薦：structlog（結構化日誌，適合 production）
# pip install structlog
# import structlog
# logger = structlog.get_logger("ecpay")
```

> **日誌安全規則**：HashKey、HashIV、CheckMacValue 為機敏資料，嚴禁出現在任何日誌、錯誤回報或前端回應中。

## AES-GCM API 參考（電子收據 V3.0+ 選用）

> **僅電子收據支援**：其他 AES-JSON 服務仍用 CBC。GCM 預設關閉，特店後台切換後才使用。完整實作見 [guides/14 §AES-GCM 模式](../14-aes-encryption.md#aes-gcm-模式電子收據選用)。

- **原生 API**：`from Crypto.Cipher import AES` → `AES.new(key, AES.MODE_GCM, nonce=iv)` → `.encrypt_and_digest(pt)` / `.decrypt_and_verify(ct, tag)`
- **依賴**：`pycryptodome`（與 CBC 共用，無新依賴）
- **Key**：`HashKey` 前 16 byte（與 CBC 共用）
- **IV / Nonce**：**12 byte 隨機**（`os.urandom(12)`），不使用 HashIV
- **Tag**：16 byte，由 `encrypt_and_digest()` 回傳第二個值
- **輸出格式**：`Base64( IV(12B) || Ciphertext || Tag(16B) )`
- **失敗處理**：Tag 驗證失敗時 `decrypt_and_verify()` raise `ValueError`，用 try/except 捕捉

## URL Encode 注意

```python
# ⚠️ Python 的 urllib.parse.quote_plus() 不會編碼 ~ 字元
# ECPay CheckMacValue 要求 ~ 編碼為 %7e
# guides/13 的 ecpayUrlEncode 已處理此轉換（~ → %7e）
# 請直接使用 guides/13 提供的函式，勿自行實作
```

## 單元測試模式

```python
# test_ecpay.py
import pytest
from unittest.mock import patch, MagicMock
from ecpay_crypto import generate_check_mac_value, aes_encrypt, aes_decrypt

# 使用 test-vectors/ 驗證加密正確性
def test_cmv_sha256():
    params = {
        "MerchantID": "3002607",
        "MerchantTradeNo": "Test1234567890",
        "MerchantTradeDate": "2025/01/01 12:00:00",
        "PaymentType": "aio",
        "TotalAmount": "100",
        "TradeDesc": "測試",
        "ItemName": "測試商品",
        "ReturnURL": "https://example.com/notify",
        "ChoosePayment": "ALL",
        "EncryptType": "1",
    }
    result = generate_check_mac_value(params, "pwFHCqoQZGmho4w6", "EkRm7iFT261dpevs")
    assert result == "291CBA324D31FB5A4BBBFDF2CFE5D32598524753AFD4959C3BF590C5B2F57FB2"

def test_aes_roundtrip():
    data = {"MerchantID": "2000132", "BarCode": "/1234567"}
    encrypted = aes_encrypt(data, "ejCk326UnaZWKisg", "q9jcZX8Ib9LM8wYk")
    decrypted = aes_decrypt(encrypted, "ejCk326UnaZWKisg", "q9jcZX8Ib9LM8wYk")
    assert decrypted["MerchantID"] == "2000132"

# Mock HTTP 測試 API 呼叫
@patch("requests.post")
def test_payment_api(mock_post):
    mock_post.return_value = MagicMock(
        status_code=200,
        json=lambda: {"TransCode": 1, "Data": "encrypted_data"}
    )
    # ... test logic
```

## Linter / Formatter

```bash
pip install ruff mypy

# pyproject.toml
# [tool.ruff]
# target-version = "py311"
# line-length = 120

# [tool.mypy]
# python_version = "3.11"
# strict = true
# warn_return_any = true
# warn_unused_configs = true

# 格式化
ruff format .
# 檢查
ruff check .
# 型別檢查（搭配 TypedDict 使用）
mypy --strict .
```
