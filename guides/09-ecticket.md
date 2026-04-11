> 對應 ECPay API 版本 | 基於 PHP SDK ecpay/sdk | 最後更新：2026-04

# 電子票證整合指南

## 概述

ECPay 電子票證服務讓商家發行和管理數位票券，適用於遊樂園門票、餐廳餐券、活動票券、課程套票等場景。使用 **AES 加密 + JSON 格式 + CheckMacValue（SHA256）**。

> ⚠️ **與其他 AES-JSON 服務的差異**：電子票證除了 AES 加密 Data 欄位外，Request 和 Response 的 JSON 最外層還包含 `CheckMacValue` 必填欄位，用於驗證資料完整性。ECPG、發票等其他 AES-JSON 服務無此欄位。

### ⚠️ 電子票證開發者必讀：三重驗證

電子票證回應為三層 JSON 結構，**必須做三項檢查**：

1. 檢查外層 `TransCode === 1`（否則 AES 加密/格式有問題）
2. AES 解密 `Data` 取得明文 JSON，**再驗證 `CheckMacValue`**（CMV 使用 Data 明文字串計算，不可在解密前驗證，公式見下方 §CheckMacValue 計算）
3. 檢查內層 `RtnCode === 1`（**整數** `1`，非字串 `'1'`）（業務邏輯問題）

完整錯誤碼參考見 [guides/21](./21-error-codes-reference.md)。

> ⚠️ **Callback 回應格式特別警告（與 AIO / ECPG 完全不同）**
>
> | 服務 | ECPay 通知格式 | 你必須回應 |
> |------|--------------|----------|
> | AIO / ECPG ReturnURL | Form POST 或 JSON POST | 純文字 `1\|OK` |
> | **電子票證 UseStatusNotifyURL** | JSON POST + AES Data + CheckMacValue | **AES 加密 JSON + CheckMacValue**（見 §步驟 3） |
>
> 若對電子票證 Callback 回應純文字 `1\|OK`，系統將視為失敗並重試，導致重複核退事件。
> 完整的各服務 Callback 格式對照見 [SKILL.md §Callback 格式速查表](../SKILL.md)。

## 前置需求

- 需向綠界申請電子票證服務（獨立開通，非金流帳號自動包含）
- 加密方式：AES-128-CBC（詳見 [guides/14-aes-encryption.md](./14-aes-encryption.md)）
- 檢查碼：CheckMacValue（SHA256），計算方式見下方 §CheckMacValue 計算
- 測試環境帳號：官方提供公開測試帳號（見 [references/Ecticket/價金保管-使用後核銷API技術文件.md](../references/Ecticket/價金保管-使用後核銷API技術文件.md) §準備事項/測試介接資訊）

> ⚠️ **ECPay 官方 PHP SDK（1.x）未包含電子票證程式碼範例。**
>
> **替代方案**：電子票證使用 **AES-JSON 協議**（與 B2C 發票類似，但額外需要 CheckMacValue），請參照：
> - [guides/04 B2C 發票](./04-invoice-b2c.md) — AES-JSON 請求結構
> - [scripts/SDK_PHP/example/Invoice/B2C/Issue.php](../scripts/SDK_PHP/example/Invoice/B2C/Issue.php) — PHP 範例（結構相同，替換 Service 和參數）
>
> 主要差異：`Service` 名稱不同（如 `IssueVoucher` vs `Issue`），參數依各 Ecticket API 規格調整。

### 測試帳號

綠界提供公開測試帳號，可直接使用（詳見 `references/Ecticket/價金保管-使用後核銷API技術文件.md` §準備事項/測試介接資訊）：
> 📋 **完整跨服務測試帳號對照表**（金流 / 物流 / 發票 / 電子票證）見 [SKILL.md §測試帳號](../SKILL.md#測試帳號)。電子票證 HashKey/HashIV 與金流、發票**完全不同**，混用導致 CheckMacValue 永遠驗證失敗。

**平台商**（價金保管-使用後核銷、純發行共用）：
- MerchantID：`3085672`（平台商帳號）
- ECTicket HashKey：`b15bd8514fed472c`
- ECTicket HashIV：`9c8458263def47cd`

> ⚠️ **平台商模式**需在 API 請求**外層 JSON** 額外帶入 `PlatformID` 參數（值與平台商 MerchantID 相同，即 `3085672`），且正式使用前須向綠界申請平台商合約。

```php
// 平台商模式 — 外層 JSON 需加上 PlatformID
$outerJson = json_encode([
    'PlatformID'    => '3085672',       // ← 平台商模式必填（特店模式不需此欄位）
    'MerchantID'    => '子特店MerchantID',
    'RqHeader'      => ['Timestamp' => time()],
    'Data'          => $encryptedData,
    'CheckMacValue' => $checkMacValue,
]);
```

**特店（純發行模式）**：
- MerchantID：`3085676`
- ECTicket HashKey：`7b53896b742849d3`
- ECTicket HashIV：`37a0ad3c6ffa428b`

**特店（價金保管-使用後核銷模式）**：
- MerchantID：`3362787`
- ECTicket HashKey：`c539115ea7674f20`
- ECTicket HashIV：`86f625e60cb1473a`

**特店（價金保管-分期核銷模式）**：
- MerchantID：`3361934`
- ECTicket HashKey：`1069c84afab54f16`
- ECTicket HashIV：`795c968d90c14971`

> ⚠️ 三種電子票證服務使用不同的測試帳號，切勿混用。分期核銷模式不支援平台商。
>
> ⚠️ 價金保管-分期核銷**不支援平台商模式**（官方測試介接資訊僅提供特店帳號）。僅純發行及價金保管-使用後核銷支援平台商。
>
> ⚠️ 金流與 ECTicket 使用**不同的** HashKey/HashIV，請使用對應的介接資訊。

測試環境 URL：`https://ecticket-stage.ecpay.com.tw`

```php
$factory = new Factory([
    'hashKey' => '7b53896b742849d3',  // 純發行特店測試帳號（價金保管請改用 c539115ea7674f20）
    'hashIv'  => '37a0ad3c6ffa428b',  // 純發行特店測試帳號（價金保管請改用 86f625e60cb1473a）
]);
// ⚠️ ECTicket 不可使用 PostWithAesJsonResponseService（會缺少外層 CheckMacValue 且已有預加密 Data 時會造成二次加密）
// 請使用 AesService + JsonCurlService（詳見 §PHP 請求範例）
```

## 🚀 首次串接：最快成功路徑

> 電子票證有三種模式，但首次開發請先選「**價金保管-使用後核銷**」（最簡單且 ECPay 代管款項風險最低）。

### 前置確認清單

- [ ] 向綠界確認電子票證服務已開通（獨立功能，金流帳號不自動包含）
- [ ] 取得 ECTicket 專用 HashKey/HashIV（與金流、發票用的**不同**）
- [ ] 了解三層驗證流程：TransCode → AES 解密 Data → **CheckMacValue（SHA256）** → RtnCode
- [ ] AES-128-CBC 已實作（見 [guides/14](./14-aes-encryption.md)）；ECTicket CheckMacValue 計算見**本指南 §CheckMacValue 計算**（⚠️ ECTicket CMV 公式與 AIO 完全不同，請勿用 guides/13 的 AIO 公式計算）
- [ ] ⚠️ **ECTicket CMV 計算公式與 AIO 不同**：完整公式為 `strtoupper( SHA256( strtolower( URLencode( HashKey + Data明文JSON + HashIV ) ) ) )`，直接串接（無 `&` / `=`）。AIO 對 `key=v&key2=v2&...` 格式計算；**兩者互不相容，切勿混用**
- [ ] ⚠️ **Callback（UseStatusNotifyURL）回應必須是 AES 加密 JSON + CheckMacValue**（不是 `1|OK`）

---

### 步驟 1：選擇票券模式

```
需要電子票證？
├── 希望 ECPay 代管款項（降低風險）
│   ├── 票券一次性使用（門票、餐券） → 價金保管 — 使用後核銷 ← 首次開發選這個
│   └── 票券多次使用（課程套票、月卡） → 價金保管 — 分期核銷
└── 自行處理金流 → 純發行 — 使用後核銷
```

> 🔍 **不確定選哪個？** 選「價金保管-使用後核銷」最安全、開發最簡單。

---

### 步驟 2：發行票券（IssueVoucher）

#### IssueType 對應必填欄位速查

| IssueType | 說明 | TicketInfo 必填欄位 |
|:---------:|------|-------------------|
| 1 | 商品編號方式 | `ItemNo`（商品編號）、`TicketAmount`（張數） |
| 2 | 序號方式（商家提供序號）| `SerialNo`（序號）、`TicketAmount`（張數） |
| 3 | 自動產生序號 | `TicketAmount`（張數） |
| 4 | 純序號名稱方式 | `ItemName`（名稱）、`TicketPrice`（單價，整數，元）、`TicketAmount`（張數） |

> 首次開發建議選 `IssueType=4`（最直覺）。序號管理需求才考慮 1/2/3。

```php
// 注意：需先 AES 加密 Data，再計算外層 CheckMacValue（見 §PHP 請求範例）
// 價金保管模式 — IssueType=4 純序號範例（不需先上架商品，最快驗證）
$data = [
    'MerchantID'         => '3362787',   // 價金保管特店測試帳號
    'PaymentProvider'    => '2',          // 必填：1=綠界金流 2=其他
    'MerchantTradeNo'    => 'TEST' . date('YmdHis'),  // 提貨券必填
    'IssueType'          => '4',          // 必填：4=純序號
    'Operator'           => 'System',     // 必填：建立人員（限英數字）
    'UseStatusNotifyURL' => 'https://你的網站/ecticket/notify',
    'TicketInfo'         => [
        [
            'ItemName'     => '測試票券',    // IssueType=4 時必填
            'TicketPrice'  => 100,           // IssueType=4 時必填（整數，單位:元）
            'TicketAmount' => 1,             // 必填：發行張數（整數）
        ],
    ],
    // 完整必填欄位依 IssueType 不同，請 web_fetch references/Ecticket/價金保管-使用後核銷API技術文件.md 確認
];
```

```python
# Python — 電子票證發行票券（IssueVoucher）
# ⚠️ ECTicket 三重驗證：TransCode → 解密 Data → CheckMacValue → RtnCode
import time, base64, json, hashlib, urllib.parse, requests as req
from Crypto.Cipher import AES
from Crypto.Util.Padding import pad, unpad

MERCHANT_ID  = '3362787'
HASH_KEY     = 'c539115ea7674f20'   # ⚠️ 價金保管特店測試帳號，不同於金流/發票
HASH_IV      = '86f625e60cb1473a'
ECTICKET_URL = 'https://ecticket-stage.ecpay.com.tw'

def ecticket_url_encode(s: str) -> str:
    """ECTicket URL encode：urlencode → 補 ~ 編碼，不做 .NET 字元還原（與 AIO 的 ecpayUrlEncode 不同）
    功能等效於 guides/14 的 aes_url_encode — 兩者都對應 PHP urlencode()，只做 URL 編碼，不做 lowercase 和 .NET 字元替換。
    Python quote_plus 已正確編碼 !*'() 等字元（與 PHP urlencode 行為一致），僅需手動補 ~ 的編碼。"""
    return urllib.parse.quote_plus(str(s)).replace('~', '%7E')

def aes_encrypt(plaintext_json: str) -> str:
    """AES-128-CBC 加密：JSON 字串 → URL encode → AES-CBC → base64（完整實作見 guides/14）"""
    url_encoded = ecticket_url_encode(plaintext_json)
    key = HASH_KEY[:16].encode('utf-8')
    iv  = HASH_IV[:16].encode('utf-8')
    cipher = AES.new(key, AES.MODE_CBC, iv)
    return base64.b64encode(cipher.encrypt(pad(url_encoded.encode('utf-8'), AES.block_size))).decode('utf-8')

def aes_decrypt(cipher_b64: str) -> dict:
    """AES-128-CBC 解密：base64 → AES 解密 → URL decode → JSON decode"""
    encrypted = base64.b64decode(cipher_b64)
    key = HASH_KEY[:16].encode('utf-8')
    iv  = HASH_IV[:16].encode('utf-8')
    cipher = AES.new(key, AES.MODE_CBC, iv)
    decrypted = unpad(cipher.decrypt(encrypted), AES.block_size).decode('utf-8')
    return json.loads(urllib.parse.unquote_plus(decrypted))

def aes_decrypt_str(cipher_b64: str) -> str:
    """AES-128-CBC 解密：base64 → AES 解密 → URL decode → 返回原始 JSON 字串（不做 json.loads）
    ⚠️ 供 CheckMacValue 驗證使用：保留原始格式（含 \\uXXXX 逸出），確保與 ECPay 計算的 CMV 一致。
    若改用 json.dumps 再序列化，ensure_ascii 的差異會導致 CMV 不符。"""
    encrypted = base64.b64decode(cipher_b64)
    key = HASH_KEY[:16].encode('utf-8')
    iv  = HASH_IV[:16].encode('utf-8')
    cipher = AES.new(key, AES.MODE_CBC, iv)
    decrypted = unpad(cipher.decrypt(encrypted), AES.block_size).decode('utf-8')
    return urllib.parse.unquote_plus(decrypted)

def calc_ecticket_cmv(data_json: str) -> str:
    """ECTicket CMV：SHA256( toLowerCase( URLEncode( HashKey + Data明文JSON + HashIV ) ) )
    ⚠️ 不使用 &、= 分隔，直接串接；不做 .NET 字元還原（與 AIO 不同）"""
    raw     = HASH_KEY + data_json + HASH_IV
    encoded = ecticket_url_encode(raw).lower()
    return hashlib.sha256(encoded.encode('utf-8')).hexdigest().upper()

def post_ecticket(endpoint: str, data: dict) -> dict:
    data_json = json.dumps(data, separators=(',', ':'))  # ensure_ascii=True (預設) 與 PHP json_encode 預設行為一致
    body = {
        'MerchantID':    MERCHANT_ID,
        'RqHeader':      {'Timestamp': int(time.time())},  # ECTicket RqHeader 僅需 Timestamp(官方規格無 Revision,與 guides/14 §使用場景表及 guides/20 §2.3 RqHeader 表一致)
        'Data':          aes_encrypt(data_json),
        'CheckMacValue': calc_ecticket_cmv(data_json),
    }

    r = req.post(f'{ECTICKET_URL}{endpoint}', json=body, timeout=10)
    res = r.json()

    # 第一重：TransCode（傳輸層）
    if res.get('TransCode') != 1:
        raise ValueError(f"傳輸錯誤: {res.get('TransMsg')}")

    # 第二重：解密 Data + 驗證 CheckMacValue（timing-safe）
    # ⚠️ 使用 aes_decrypt_str 取得原始 JSON 字串（勿用 json.dumps 再序列化—
    #    ensure_ascii 差異會改變 \uXXXX 逸出格式，導致 CMV 與 ECPay 計算結果不符）
    received_cmv   = res.pop('CheckMacValue', '')
    resp_data_str  = aes_decrypt_str(res['Data'])   # 原始 URL-decoded JSON 字串，格式與 ECPay 計算 CMV 時一致
    import hmac as _hmac
    if not _hmac.compare_digest(calc_ecticket_cmv(resp_data_str), received_cmv.upper()):
        raise ValueError('CheckMacValue 驗證失敗（回應偽造或 HashKey 錯誤）')

    # 第三重：確認業務層 RtnCode
    return json.loads(resp_data_str)

# 發行票券（價金保管 — IssueType=4 純序號範例）
try:
    data = post_ecticket('/api/issuance/issue', {
        'MerchantID':         MERCHANT_ID,
        'PaymentProvider':    '2',        # 必填：1=綠界金流 2=其他
        'MerchantTradeNo':    'EC' + str(int(time.time())),  # 提貨券必填
        'IssueType':          '4',          # 必填：4=純序號（不需先上架商品）
        'Operator':           'System',     # 必填：建立人員（限英數字）
        'UseStatusNotifyURL': 'https://YOUR-NGROK-URL.ngrok-free.app/ecticket/notify',
        'TicketInfo': [{
            'ItemName':    '遊樂園入場券',  # IssueType=4 時必填
            'TicketPrice': 500,             # IssueType=4 時必填（整數，單位:元）
            'TicketAmount': 1,              # 必填：發行張數（整數）
        }],
        # ⚠️ 完整欄位依 IssueType 不同，請 web_fetch references/Ecticket/ 對應文件確認
    })
    if data.get('RtnCode') == 1:  # 整數比較（AES-JSON 解密後為 Int）
        print(f"✅ 票券發行資料接收成功！TicketTradeNo={data.get('TicketTradeNo')}")
        # ⚠️ RtnCode=1 僅代表資料接收成功，需再呼叫「查詢票券發行結果 API」確認實際發行結果
    else:
        print(f"❌ 業務錯誤 RtnCode={data.get('RtnCode')} RtnMsg={data.get('RtnMsg')}")
except ValueError as e:
    print(f'❌ 錯誤：{e}')
```

> ✅ **成功時的預期輸出**：
> ```
> ✅ 票券發行資料接收成功！TicketTradeNo=2026030215301234
> ```
> ⚠️ 此 RtnCode=1 僅代表發行資料接收成功。電子票券/純序號為非同步排程發行（約 5 分鐘），需再呼叫「查詢票券發行結果 API」確認是否發行成功。

> 🔍 **此步驟失敗？**
>
> | 症狀 | 最可能原因 |
> |------|----------|
> | TransCode ≠ 1 | AES Key/IV 錯誤；或錯用金流/發票的 HashKey |
> | CheckMacValue 驗證失敗 | ECTicket 的 CMV 公式：`strtoupper(sha256(strtolower(urlencode(key+dataJson+iv))))`，不做 .NET 字元還原，與 AIO 的 `ecpayUrlEncode` 不同，見本文 §CheckMacValue 計算 |
> | RtnCode ≠ 1 | 業務參數錯誤，查 [guides/21](./21-error-codes-reference.md) |

---

### 步驟 3：接收 UseStatusNotifyURL 核退通知

核退通知的回應格式與一般 Callback 不同：**必須回應 AES 加密 JSON + CheckMacValue**（不是純文字 `1|OK`）。

```php
// 驗證收到的通知 TransCode → 解密 Data → 驗證 CheckMacValue（ECTicket 公式）→ 處理核銷 → 回應
// ⚠️ HashKey/HashIV 必須與 Factory 設定一致（ECTicket 專用帳號）
$hashKey  = 'c539115ea7674f20';  // 價金保管特店測試帳號（純發行請改用 7b53896b742849d3）
$hashIv   = '86f625e60cb1473a';  // 價金保管特店測試帳號（純發行請改用 37a0ad3c6ffa428b）
$jsonBody = json_decode(file_get_contents('php://input'), true);

// 1. 確認外層傳輸成功（TransCode === 1 代表 ECPay 端無異常）
if (($jsonBody['TransCode'] ?? null) !== 1) {
    http_response_code(400);
    exit;
}

// 2. 驗證 CheckMacValue（ECTicket 公式：HashKey + Data明文JSON + HashIV → URLEncode → toLowerCase → SHA256）
// ⚠️ 必須先解密 Data 取明文 JSON 字串，才能計算 CMV（不同於 AIO 的外層欄位排序公式）
$receivedCMV   = $jsonBody['CheckMacValue'];
$key16         = substr($hashKey, 0, 16);
$iv16          = substr($hashIv, 0, 16);
$encryptedRaw  = base64_decode($jsonBody['Data']);
$urlEncoded    = openssl_decrypt($encryptedRaw, 'aes-128-cbc', $key16, OPENSSL_RAW_DATA, $iv16);
$dataPlaintext = urldecode($urlEncoded);   // 明文 JSON 字串
$cmvRaw        = $hashKey . $dataPlaintext . $hashIv;
$expectedCMV   = strtoupper(hash('sha256', strtolower(urlencode($cmvRaw))));
if (!hash_equals($expectedCMV, $receivedCMV)) {
    http_response_code(400);
    exit;
}

// 3. 解析解密後資料（$dataPlaintext 已是 URL-decoded 明文 JSON 字串）
$data = json_decode($dataPlaintext, true);
// $data 包含：RtnCode, TicketNo, UseStatus 等

// 4. 處理核退邏輯
if (($data['RtnCode'] ?? null) === 1) {
    // 核退成功，更新票券狀態
}

// 5. 回應必須是 AES 加密 JSON + CheckMacValue（ECTicket 公式，不是 1|OK）
// ⚠️ 此處不依賴 $factory，直接用 openssl_encrypt 手動加密（與上方解密保持一致）
$respPlaintext = json_encode(['RtnCode' => 1, 'RtnMsg' => '成功'], JSON_UNESCAPED_UNICODE);
$respUrlEnc    = urlencode($respPlaintext);
$responseData  = base64_encode(openssl_encrypt($respUrlEnc, 'aes-128-cbc', $key16, OPENSSL_RAW_DATA, $iv16));
$responseCMV   = strtoupper(hash('sha256', strtolower(urlencode($hashKey . $respPlaintext . $hashIv))));
$responseBody  = [
    'MerchantID'    => '3362787',
    'RpHeader'      => ['Timestamp' => time()],
    'TransCode'     => 1,
    'TransMsg'      => '',
    'Data'          => $responseData,
    'CheckMacValue' => $responseCMV,
];
header('Content-Type: application/json');
echo json_encode($responseBody);
```

```python
# Python / Flask — UseStatusNotifyURL 核退通知接收
# ⚠️ 回應必須是 AES-JSON + CheckMacValue，不是 '1|OK'
from flask import Flask, request, Response
from Crypto.Cipher import AES
from Crypto.Util.Padding import unpad
import base64, time, json, hmac, hashlib, urllib.parse

# 使用前面定義的 ecticket_url_encode, aes_encrypt, calc_ecticket_cmv

def aes_decrypt_str(cipher_b64: str) -> str:
    """AES-128-CBC 解密→取得 URL-decoded 明文 JSON 字串（供 CMV 驗證使用）"""
    encrypted = base64.b64decode(cipher_b64)
    key_b = HASH_KEY[:16].encode('utf-8')
    iv_b  = HASH_IV[:16].encode('utf-8')
    cipher = AES.new(key_b, AES.MODE_CBC, iv_b)
    return urllib.parse.unquote_plus(
        unpad(cipher.decrypt(encrypted), AES.block_size).decode('utf-8')
    )

app = Flask(__name__)

@app.route('/ecticket/notify', methods=['POST'])
def ecticket_notify():
    body = request.get_json(force=True)

    # 第一重：TransCode（API 傳輸成功）
    if body.get('TransCode') != 1:
        return '', 400

    # 第二重：解密 Data → 驗證 CheckMacValue（ECTicket 公式：key+Data明文JSON+iv）
    # ⚠️ ECTicket CMV 需要先解密取明文 JSON 字串，才能計算（不同於 AIO 用外層欄位計算）
    received_cmv      = body.pop('CheckMacValue', '')
    data_plaintext    = aes_decrypt_str(body.get('Data', ''))   # 明文 JSON 字串
    if not hmac.compare_digest(calc_ecticket_cmv(data_plaintext), received_cmv.upper()):
        return '', 400

    # 第三重：解析解密後資料，確認業務結果
    data       = json.loads(data_plaintext)
    rtn_code   = data.get('RtnCode', 0)
    ticket_no  = data.get('TicketNo', '')
    use_status = data.get('UseStatus', '')  # 1=核銷 2=退款

    if rtn_code == 1:
        print(f'[ECTicket] ✅ 核退成功 票券={ticket_no} 狀態={use_status}')
        # TODO: 更新票券狀態為「已核銷」或「已退款」
    else:
        print(f'[ECTicket] ❌ 核退失敗 RtnCode={rtn_code}')

    # ⚠️ 回應必須是 AES-JSON + CheckMacValue（不是 '1|OK'）
    resp_json     = json.dumps({'RtnCode': 1, 'RtnMsg': '成功'}, ensure_ascii=False, separators=(',', ':'))
    response_body = {
        'MerchantID': MERCHANT_ID,
        'RpHeader':   {'Timestamp': int(time.time())},
        'TransCode':  1,
        'TransMsg':   '',
        'Data':       aes_encrypt(resp_json),
    }
    # 回應 CMV 使用 ECTicket 公式：key + DataPlaintext + iv（見 §CheckMacValue 計算 情境 A）
    response_body['CheckMacValue'] = calc_ecticket_cmv(resp_json)
    return Response(json.dumps(response_body), content_type='application/json')
```

> 🔍 **核退通知一直重送？** 確認回應格式正確：Data 需 AES 加密，`RtnCode=1` 且外層有 CheckMacValue。

---

## HTTP 協議速查（非 PHP 語言必讀）

| 項目 | 規格 |
|------|------|
| 協議模式 | AES-JSON + CheckMacValue（SHA256）— 詳見 [guides/20-http-protocol-reference.md](./20-http-protocol-reference.md) |
| HTTP 方法 | POST |
| Content-Type | `application/json` |
| 認證 | AES-128-CBC 加密 Data 欄位 + **CheckMacValue** 必填 — 詳見 [guides/14-aes-encryption.md](./14-aes-encryption.md) |
| 測試環境 | `https://ecticket-stage.ecpay.com.tw` |
| 正式環境 | `https://ecticket.ecpay.com.tw` |
| 回應結構 | 三層 JSON（TransCode → 解密 Data → 驗證 CheckMacValue → RtnCode） |
| 測試帳號 | 官方提供公開測試帳號（見 §測試帳號 或 references/Ecticket/ §準備事項/測試介接資訊） |
| UseStatusNotifyURL 回應格式 | 收到核退通知後，回應 AES 加密 JSON + **CheckMacValue**（Data 內 `{"RtnCode": 1, "RtnMsg": "成功"}`）|

> **核退通知（UseStatusNotifyURL）**：電子票證退款/核退時，ECPay 會 POST AES-JSON 通知到你的 UseStatusNotifyURL。
> 驗證方式：AES 解密 Data 欄位 + **驗證 CheckMacValue**（與發送 API 相同的 HashKey/HashIV）。
> 必須回應 AES 加密 JSON + **CheckMacValue**（Data 內 `{"RtnCode": 1, "RtnMsg": "成功"}`），否則每 5-15 分鐘重送，每日最多 4 次。詳見 [guides/22 §Callback 總覽表](./22-webhook-events-reference.md)。

## 模式選擇決策樹

```
需要電子票證？
├── 希望 ECPay 代管款項（降低風險）
│   ├── 票券一次性使用（門票、餐券） → 價金保管 — 使用後核銷
│   └── 票券多次使用（課程套票、月卡） → 價金保管 — 分期核銷
└── 自行處理金流
    └── 純發行 — 使用後核銷
```

### 三種模式快速比較

| 面向 | 價金保管-使用後核銷 | 價金保管-分期核銷 | 純發行-使用後核銷 |
|------|:---:|:---:|:---:|
| 款項代管 | ECPay 代管 | ECPay 代管 | **商家自行處理** |
| 金流風險 | 低（ECPay 保管） | 低（ECPay 保管） | **高（自行負責）** |
| 開發複雜度 | ★★☆ | ★★★ | ★★★ |
| 核銷方式 | 一次核銷 | 分次核銷 | 一次核銷 |
| 適用場景 | 門票、餐券、入場券 | 課程套票、月卡、多次券 | 自有金流體系的票券 |
| 結算時機 | 核銷後撥款 | 每次核銷後撥款 | 不經 ECPay |
| 推薦度 | **入門首選** | 進階 | 特殊需求 |

> **不確定選哪個？** 選「價金保管-使用後核銷」最安全，開發最簡單。

## 三種模式詳解

### 價金保管 — 使用後核銷（推薦入門）

**流程**：消費者購買 → ECPay 代管款項 → 消費者使用票券 → 商家核銷 → ECPay 結算給商家

```
消費者購買票券（透過金流 AIO/站內付 2.0）
    → ECPay 代管款項
    → 發行票券（API）
    → 消費者取得票券 QR Code / 序號
    → 消費者到場使用
    → 商家呼叫核銷 API
    → ECPay 結算款項給商家
```

**適用場景**：遊樂園門票、景點入場券、餐廳餐券、活動票券

**API 端點**（12 個功能）— 端點來源：官方 API 技術文件

#### 完整 API 端點表

| 分類 | 操作 | HTTP Method | 端點路徑 |
|------|------|------------|---------|
| 票券作業 | 票券發行 | POST | `/api/issuance/issue` |
| 票券作業 | 票券核銷 | POST | `/api/Ticket/WriteOff` |
| 票券作業 | 票券退貨 | POST | `/api/issuance/Refund` |
| 查詢作業 | 查詢履約保障天期 | POST | `/api/Ticket/EscrowDay` |
| 查詢作業 | 查詢商品資訊 | POST | `/api/Ticket/QueryItemInfo` |
| 查詢作業 | 批次查詢商品資訊 | POST | `/api/Ticket/BatchQueryItemInfo` |
| 查詢作業 | 查詢票券發行結果 | POST | `/api/Ticket/QueryIssueResult` |
| 查詢作業 | 取得紙本票面資料 | POST | `/api/Ticket/QueryPrintData` |
| 查詢作業 | 查詢票券明細 | POST | `/api/Ticket/QueryTicketStatus` |
| 查詢作業 | 查詢訂單資訊 | POST | `/api/Ticket/QueryOrderInfo` |
| 查詢作業 | 下載訂單明細檔 | POST | `/api/Ticket/OrderDetail` |
| 主動通知 | 核退主動通知 | POST（綠界→你） | 由你提供 UseStatusNotifyURL |

> ⚠️ 價金保管模式的票券發行與退貨使用不同的基礎路徑 `/api/issuance/`，其餘 API（核銷、查詢）仍使用 `/api/Ticket/`。

#### PHP 請求範例（票券發行）

> ⚠️ **重要**：電子票證**不可使用** `PostWithAesJsonResponseService`，因為該 Service 無法在外層 JSON 加上必填的 `CheckMacValue`。需改用 `AesService` 手動加密 + 計算 CheckMacValue + `JsonCurlService` 直接 POST。
>
> ⚠️ **票券資料使用 `TicketInfo` 陣列**：所有 IssueType 的票券資料都放在 `TicketInfo` 陣列內（每個元素為一組商品）。IssueType=4（純序號）時每個元素須含 `ItemName` + `TicketPrice` + `TicketAmount`；IssueType≠4 時須含 `ItemNo` + `TicketAmount`。各 IssueType 的完整必填欄位請 `web_fetch references/Ecticket/` 對應文件確認。

```php
use Ecpay\Sdk\Services\AesService;

$hashKey    = 'c539115ea7674f20';  // 價金保管特店測試帳號
$hashIv     = '86f625e60cb1473a';
$merchantId = '3362787';  // 價金保管特店測試帳號

// 1. 準備 Data 明文（IssueType=4 純序號範例）
$data = [
    'MerchantID'         => $merchantId,
    'PaymentProvider'    => '2',     // 必填：1=綠界金流 2=其他
    'IssueType'          => '4',     // 必填：4=純序號（不需先上架商品）
    'Operator'           => 'System', // 必填：建立人員（限英數字）
    'UseStatusNotifyURL' => 'https://你的網站/ecticket/notify',
    'TicketInfo'         => [
        [
            'ItemName'     => '遊樂園入場券',  // IssueType=4 時必填
            'TicketPrice'  => 500,              // IssueType=4 時必填
            'TicketAmount' => 1,                // 必填：發行張數
        ],
    ],
];

// 2. AES 加密 Data（SDK AesService::encrypt 內部執行 json_encode + urlencode + AES-128-CBC + base64）
$aesService    = $factory->create(AesService::class);
$encryptedData = $aesService->encrypt($data);

// 3. 計算 CheckMacValue（對 Data 明文的 json_encode 結果，需與 AesService 內部一致）
// 公式：strtoupper( SHA256( toLowerCase( URLEncode( HashKey + DataJson + HashIV ) ) ) )
$dataJson      = json_encode($data);  // 無 JSON flags，與 AesService::encrypt() 內部一致
                                       // ⚠️ 若 Data 含非 ASCII 字元（如中文），PHP 預設以 \uXXXX 逸出；
                                       // AesService::encrypt() 同樣使用預設 flags，兩者一致。
$checkMacValue = strtoupper(hash('sha256', strtolower(urlencode($hashKey . $dataJson . $hashIv))));

// 4. 組裝外層 JSON（必須包含 CheckMacValue）
$outerJson = json_encode([
    'MerchantID'    => $merchantId,
    'RqHeader'      => ['Timestamp' => time()],  // ECTicket RqHeader 僅需 Timestamp
    'Data'          => $encryptedData,
    'CheckMacValue' => $checkMacValue,
]);

// 5. 直接 POST（不透過 PostService，避免 Data 被二次加密）
$curlService = $factory->create('JsonCurlService');
try {
    $raw      = $curlService->run($outerJson, 'https://ecticket-stage.ecpay.com.tw/api/issuance/issue');
    $response = json_decode($raw, true);
    // 三層驗證：TransCode → 解密 Data → CheckMacValue → RtnCode（詳見 §三重驗證）
} catch (\Exception $e) {
    error_log('ECPay Ticket Issue Error: ' . $e->getMessage());
}
```

#### PHP 請求範例（票券核銷）

```php
// 核銷範例（同票券發行，需手動計算 CheckMacValue + 使用 JsonCurlService）
$data = [
    'MerchantID' => $merchantId,
    'WriteOffNo' => 'LEU4loiSZ9TDTWAtxL',  // 必填：掃描票券 Barcode 取得的核銷代碼
    'Action'     => '1',                     // 必填：1=核銷 2=取消核銷
    'Operator'   => 'System',                // 必填：操作人員（限英數字）
];
$encryptedData = $aesService->encrypt($data);
$dataJson      = json_encode($data);
$checkMacValue = strtoupper(hash('sha256', strtolower(urlencode($hashKey . $dataJson . $hashIv))));
$outerJson     = json_encode([
    'MerchantID'    => $merchantId,
    'RqHeader'      => ['Timestamp' => time()],  // ECTicket RqHeader 僅需 Timestamp
    'Data'          => $encryptedData,
    'CheckMacValue' => $checkMacValue,
]);
try {
    $raw      = $curlService->run($outerJson, 'https://ecticket-stage.ecpay.com.tw/api/Ticket/WriteOff');
    $response = json_decode($raw, true);
} catch (\Exception $e) {
    error_log('ECPay Ticket WriteOff Error: ' . $e->getMessage());
}
```

### 價金保管 — 分期核銷

**流程**：與「使用後核銷」類似，但票券可多次使用，每次核銷部分金額。

```
消費者購買 10 堂課程套票（$5000）
    → ECPay 代管 $5000
    → 每次上課核銷 $500（1/10）
    → 第 10 次核銷後全額結算
    → 中途退票：已核銷部分結算，未核銷部分退款
```

**適用場景**：課程套票、健身房月卡、多次入場券

**API 端點**（6 個功能）— 端點來源：官方 API 技術文件

#### 完整 API 端點表

| 分類 | 操作 | HTTP Method | 端點路徑 |
|------|------|------------|---------|
| 退貨 | 訂單退貨 | POST | `/api/issuance/Refund` |
| 查詢作業 | 查詢履約保障天期 | POST | `/api/Ticket/EscrowDay` |
| 查詢作業 | 查詢訂單退款資訊 | POST | `/api/Ticket/QueryRefundInfo` |
| 查詢作業 | 查詢訂單資訊 | POST | `/api/Ticket/QueryOrderInfo` |
| 查詢作業 | 下載訂單明細檔 | POST | `/api/Ticket/OrderDetail` |
| 主動通知 | 退款主動通知 | POST（綠界→你） | 由你提供 UseStatusNotifyURL |

> ⚠️ 價金保管模式的票券發行與退貨使用不同的基礎路徑 `/api/issuance/`，其餘 API（核銷、查詢）仍使用 `/api/Ticket/`。

> **與使用後核銷差異**：分期核銷模式的票券發行和核銷由綠界後台管理，API 主要處理退貨和查詢。每次核銷部分金額，適合多次使用的票券場景。

### 純發行 — 使用後核銷

**流程**：商家自行處理金流，ECPay 僅提供票券發行和管理。

```
消費者在你的網站付款（自行處理）
    → 你的伺服器呼叫 ECPay 發行票券
    → 消費者取得票券
    → 消費者使用時核銷
    → 無 ECPay 結算（你自行處理）
```

**適用場景**：已有金流管道的商家、禮物券、兌換券

**API 端點**(14 個功能)— 端點來源:ECPay developers.ecpay.com.tw 官方文件(URL 清單見 `references/Ecticket/純發行-使用後核銷API技術文件.md`)

> ⚠️ **純發行模式端點路徑特別注意**:本表列出的純發行模式端點使用 `/api/Ticket/` 前綴(如 `/api/Ticket/Issue`、`/api/Ticket/Refund`),與**價金保管模式**使用的 `/api/issuance/issue` 前綴**不同**。部分本文件其他範例為歷史範例使用 `/api/issuance/issue`(價金保管模式路徑),切勿混用。

#### 完整 API 端點表

| 分類 | 操作 | HTTP Method | 端點路徑 |
|------|------|------------|---------|
| 票券作業 | 票券發行 | POST | `/api/Ticket/Issue` |
| 票券作業 | 票券核銷 | POST | `/api/Ticket/WriteOff` |
| 票券作業 | 票券退貨 | POST | `/api/Ticket/Refund` |
| 查詢作業 | 查詢履約保障天期 | POST | `/api/Ticket/EscrowDay` |
| 查詢作業 | 查詢商品資訊 | POST | `/api/Ticket/QueryItemInfo` |
| 查詢作業 | 批次查詢商品資訊 | POST | `/api/Ticket/BatchQueryItemInfo` |
| 查詢作業 | 查詢票券發行結果 | POST | `/api/Ticket/QueryIssueResult` |
| 查詢作業 | 取得紙本票面資料 | POST | `/api/Ticket/QueryPrintData` |
| 查詢作業 | 查詢票券明細 | POST | `/api/Ticket/QueryTicketStatus` |
| 查詢作業 | 查詢訂單退款資訊 | POST | `/api/Ticket/QueryRefundInfo` |
| 查詢作業 | 查詢訂單資訊 | POST | `/api/Ticket/QueryOrderInfo` |
| 查詢作業 | 下載訂單明細檔 | POST | `/api/Ticket/OrderDetail` |
| 主動通知 | 退款主動通知 | POST（綠界→你） | 由你提供 UseStatusNotifyURL |
| 主動通知 | 核退主動通知 | POST（綠界→你） | 由你提供 UseStatusNotifyURL |

> **與價金保管-使用後核銷差異**：純發行模式額外包含「查詢訂單退款資訊」和「退款主動通知」，因為退貨退款由商家自行處理，需獨立追蹤退款狀態。價金保管-使用後核銷模式的退款由 ECPay 代管處理，不需這兩個 API。

## 價金保管 vs 純發行 比較

| 面向 | 價金保管 | 純發行 |
|------|---------|--------|
| 金流處理 | ECPay 代管款項 | 商家自行處理 |
| 結算時機 | 核銷後結算 | 無結算機制 |
| 風險 | 低（ECPay 保管） | 商家自負 |
| 退票退款 | ECPay 自動處理 | 商家自行處理 |
| 手續費 | 較高（含代管服務） | 較低（僅票券管理） |
| 適用 | 高單價、跨商家、需信任保障 | 自家使用、低成本 |

## CheckMacValue 計算

電子票證的 CheckMacValue 分為兩種情境，公式**不同**：

### 情境 A：商家發送請求給 ECPay（outgoing request）

```
CheckMacValue = strtoupper( SHA256( toLowerCase( URLEncode( HashKey + Data明文JSON + HashIV ) ) ) )
```

**計算步驟**：

1. 取得 Data 欄位的**明文**（加密前的 JSON 字串）
2. 在明文前加上 HashKey、後加上 HashIV
3. 對整串字串進行 URL Encode
4. 轉為小寫
5. 以 SHA256 產生雜湊值
6. 轉為大寫（strtoupper）→ 即為 CheckMacValue

> ⚠️ **與 AIO 金流 CheckMacValue 的差異**：
> - AIO 金流：將各參數依字母排序、以 `&` 串接、前後加 HashKey/HashIV → URLEncode → SHA256
> - 電子票證：直接取 Data **JSON 明文整段**、前後加 HashKey/HashIV → URLEncode → toLowerCase → SHA256
>
> 兩者 URLEncode 規則不同，不可混用：
> - **AIO 金流**使用 `ecpayUrlEncode`（urlencode → strtolower → .NET 特殊字元還原，如 %21→!）
> - **電子票證**使用簡化版（`urlencode` → `strtolower` 僅此兩步，**不做 .NET 特殊字元還原**）
>
> 詳見官方附錄：[檢查碼機制](https://developers.ecpay.com.tw/29998.md)。
> 倉庫內 `scripts/SDK_PHP/example/Ecticket/README.md` 提供的是高階整合提醒；**⚠️ 注意：該 README 誤標 CheckMacValue 為「AIO 公式」，實際上電子票證 CMV 公式與 AIO 完全不同**（無排序、無 .NET 字元還原），正確公式以此處說明與 `test-vectors/checkmacvalue.json` 的 **E-Ticket CMV 公式測試** 為準。

### 情境 B：ECPay 發送 Callback 給商家（incoming callback）

ECPay 發出的核退通知（`UseStatusNotifyURL`）中，CheckMacValue 使用與情境 A **相同的 ECTicket 公式**，以 Data 的**明文 JSON**計算（非 AIO 外層欄位排序公式）：

```
CheckMacValue = strtoupper( SHA256( toLowerCase( URLEncode( HashKey + Data明文JSON + HashIV ) ) ) )
```

因此驗證流程**必須先解密 Data 取得明文，才能計算 CMV**：

```
// 驗證步驟：TransCode → 解密取明文 JSON → 計算 ECTicket CMV → 比對 → 處理 RtnCode
$key16         = substr($hashKey, 0, 16);
$iv16          = substr($hashIv, 0, 16);
$urlEncoded    = openssl_decrypt(base64_decode($body['Data']), 'aes-128-cbc', $key16, OPENSSL_RAW_DATA, $iv16);
$dataPlaintext = urldecode($urlEncoded);                                  // 明文 JSON 字串
$cmvRaw        = $hashKey . $dataPlaintext . $hashIv;
$expectedCmv   = strtoupper(hash('sha256', strtolower(urlencode($cmvRaw))));  // ECTicket 公式
if (!hash_equals($expectedCmv, $body['CheckMacValue'])) { /* 400 */ }
$data = json_decode($dataPlaintext, true);                                // 再 JSON decode 取資料
```

> ⚠️ **常見錯誤**：用 AIO 公式（對外層欄位 MerchantID/RqHeader/TransCode/Data 排序計算）驗證 ECTicket callback CMV 將**永遠驗證失敗**。ECTicket 的 CMV 公式與 AIO 金流完全不同，詳見本文 §CheckMacValue 計算 情境 A。

商家**回應** ECPay 時，同樣以 **ECTicket 公式**對回應 Data 明文計算 CheckMacValue（外層使用 `RpHeader`，不是 `RqHeader`）。

## 請求格式

所有電子票證 API 都使用 AES 三層結構 + CheckMacValue（端點來源：官方 API 技術文件）：

```php
// 1. 準備 Data 明文
$data = [
    'MerchantID' => '你的MerchantID',
    // 業務參數（票券資訊、核銷資訊等）
];
$dataJson = json_encode($data);  // 無 JSON flags，與 AesService::encrypt() 內部一致

// 2. 計算 CheckMacValue（對 Data 明文 $dataJson）
$cmvRaw        = $hashKey . $dataJson . $hashIv;
$checkMacValue = strtoupper(hash('sha256', strtolower(urlencode($cmvRaw))));

// 3. AES 加密 Data（使用 AesService，傳入 array 讓 SDK 內部 json_encode）
$aesService    = $factory->create(\Ecpay\Sdk\Services\AesService::class);
$encryptedData = $aesService->encrypt($data);

// 4. 組裝完整請求
$input = [
    'MerchantID'    => '你的MerchantID',
    'RqHeader'      => ['Timestamp' => time()],  // ECTicket RqHeader 僅需 Timestamp
    'Data'          => $encryptedData,
    'CheckMacValue' => $checkMacValue,
];

// 5. 直接 POST（不透過 PostWithAesJsonResponseService，避免 Data 被二次加密）
$curlService = $factory->create('JsonCurlService');
$raw         = $curlService->run(json_encode($input), 'https://ecticket-stage.ecpay.com.tw/api/issuance/issue');
                                                       // ⚠️ 端點依模式：價金保管 /api/issuance/issue ；純發行 /api/Ticket/Issue
$response    = json_decode($raw, true);
```

> **注意**：電子票證的 PHP SDK 沒有提供範例程式碼。上述程式碼展示 AES 請求格式，
> 具體必填參數請參考官方 API 技術文件。
>
> ⚠️ **SNAPSHOT 2026-03** | 來源：`references/Ecticket/價金保管-使用後核銷API技術文件.md`（及同目錄其他 reference 檔案）
> 以上參數僅供整合流程理解，不可直接作為程式碼生成依據。**生成程式碼前必須 web_fetch 來源文件取得最新規格。**

## 與金流的搭配

典型的票券銷售流程需要搭配金流：

```
步驟 1: 消費者選購票券
步驟 2: 使用 AIO 或站內付 2.0 收款（見 guides/01 或 02）
步驟 3: 付款成功後，呼叫電子票證 API 發行票券
步驟 4: 將票券序號/QR Code 發送給消費者
步驟 5: 消費者到場使用時，呼叫核銷 API
步驟 6: 如需開發票，搭配電子發票 API（見 guides/04）
```

如需完整的跨服務整合範例，請參考 [guides/11-cross-service-scenarios.md](./11-cross-service-scenarios.md)。

## 非 PHP 語言 HTTP 範例（Node.js / Python）

電子票證使用 AES-JSON + CheckMacValue 協議。以下為 Node.js 票券發行範例：

```javascript
const crypto = require('crypto');

// 測試帳號（官方公開測試資訊，見 references/Ecticket/ §準備事項/測試介接資訊）
const MERCHANT_ID = '3362787';
const HASH_KEY = 'c539115ea7674f20';
const HASH_IV = '86f625e60cb1473a';
const BASE_URL = 'https://ecticket-stage.ecpay.com.tw';

// AES 加密 — 完整實作見 guides/14
function aesEncrypt(data, hashKey, hashIV) {
  const json = JSON.stringify(data);
  const urlEncoded = encodeURIComponent(json)
    .replace(/%20/g, '+').replace(/~/g, '%7E')
    .replace(/!/g, '%21').replace(/\*/g, '%2A')
    .replace(/'/g, '%27').replace(/\(/g, '%28').replace(/\)/g, '%29');
  const key = Buffer.from(hashKey.substring(0, 16), 'utf8');
  const iv = Buffer.from(hashIV.substring(0, 16), 'utf8');
  const cipher = crypto.createCipheriv('aes-128-cbc', key, iv);
  let encrypted = cipher.update(urlEncoded, 'utf8', 'base64');
  encrypted += cipher.final('base64');
  return encrypted;
}

// 電子票證 CheckMacValue 計算（與 AIO 金流不同！）
function calcEcticketCMV(dataPlaintext, hashKey, hashIV) {
  const raw = hashKey + dataPlaintext + hashIV;
  const urlEncoded = encodeURIComponent(raw)
    .replace(/%20/g, '+').replace(/~/g, '%7E')
    .replace(/!/g, '%21').replace(/\*/g, '%2A')
    .replace(/'/g, '%27').replace(/\(/g, '%28').replace(/\)/g, '%29')
    .toLowerCase();
  return crypto.createHash('sha256').update(urlEncoded).digest('hex').toUpperCase();
}

// 票券發行（價金保管 — IssueType=4 純序號範例）
async function issueTicket() {
  const ticketData = {
    MerchantID: MERCHANT_ID,
    PaymentProvider: '2',         // 必填：1=綠界金流 2=其他
    IssueType: '4',               // 必填：4=純序號（不需先上架商品）
    Operator: 'System',           // 必填：建立人員（限英數字）
    UseStatusNotifyURL: 'https://your-domain.com/ecticket/notify',
    TicketInfo: [
      {
        ItemName: '遊樂園入場券',   // IssueType=4 時必填
        TicketPrice: 500,           // IssueType=4 時必填（整數，單位:元）
        TicketAmount: 1,            // 必填：發行張數（整數）
      },
    ],
  };

  const dataJson = JSON.stringify(ticketData);
  const checkMacValue = calcEcticketCMV(dataJson, HASH_KEY, HASH_IV);

  const body = JSON.stringify({
    MerchantID: MERCHANT_ID,
    RqHeader: { Timestamp: Math.floor(Date.now() / 1000) },  // ECTicket RqHeader 僅需 Timestamp
    Data: aesEncrypt(ticketData, HASH_KEY, HASH_IV),
    CheckMacValue: checkMacValue,
  });

  const res = await fetch(`${BASE_URL}/api/issuance/issue`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body,
  });
  const result = await res.json();

  // 三重驗證
  // 第一重：TransCode
  if (result.TransCode !== 1) {
    throw new Error(`傳輸層錯誤: ${result.TransMsg}`);
  }

  // 第二重：解密 Data → 驗證 CheckMacValue（ECTicket 公式）
  const receivedCmv = result.CheckMacValue || '';
  const decipher = crypto.createDecipheriv(
    'aes-128-cbc',
    Buffer.from(HASH_KEY.substring(0, 16), 'utf8'),
    Buffer.from(HASH_IV.substring(0, 16), 'utf8'),
  );
  const decrypted = Buffer.concat([
    decipher.update(Buffer.from(result.Data, 'base64')),
    decipher.final(),
  ]);
  const dataPlaintext = decodeURIComponent(
    decrypted.toString('utf8').replace(/\+/g, '%20'),
  );

  const expectedCmv = calcEcticketCMV(dataPlaintext, HASH_KEY, HASH_IV);
  const receivedBuf = Buffer.from(receivedCmv);
  const expectedBuf = Buffer.from(expectedCmv);
  if (receivedBuf.length !== expectedBuf.length ||
      !crypto.timingSafeEqual(receivedBuf, expectedBuf)) {
    throw new Error('CheckMacValue 驗證失敗');
  }

  // 第三重：RtnCode（整數 1）
  const data = JSON.parse(dataPlaintext);
  if (data.RtnCode !== 1) {
    throw new Error(`業務錯誤: RtnCode=${data.RtnCode}, RtnMsg=${data.RtnMsg}`);
  }

  return data;
}
```

> 上述 `aesEncrypt` 為簡化版。完整加密/解密實作（含 PKCS7 padding、URL decode）見 [guides/14-aes-encryption.md](./14-aes-encryption.md) §Node.js。
> 其他語言開發者：電子票證的 AES 加密方式與 B2C 發票相同，可複用 guides/14 的加密函式。但 **CheckMacValue 計算為電子票證獨有**，須額外實作。

### Python 票券發行 + 核銷範例

> ⚠️ 非官方 SDK 範例 — 官方 PHP SDK v4.x 未包含電子票證範例，以下為根據 API 規格手寫的 Python 實作。
> 加密函式完整實作見 [guides/14 §Python](./14-aes-encryption.md)。

```python
import hashlib, json, base64, time, requests
from urllib.parse import quote_plus
from Crypto.Cipher import AES
from Crypto.Util.Padding import pad

# 測試帳號（官方公開測試資訊，見 references/Ecticket/ §準備事項/測試介接資訊）
MERCHANT_ID = '3362787'
HASH_KEY = 'c539115ea7674f20'
HASH_IV = '86f625e60cb1473a'
BASE_URL = 'https://ecticket-stage.ecpay.com.tw'

def ecticket_url_encode(s: str) -> str:
    """ECTicket URL encode — 用於 AES 加密前處理與 CMV 計算，與 AIO 的 ecpayUrlEncode 不同（不含 .NET 字元還原步驟）"""
    return quote_plus(str(s)).replace('~', '%7E')

def aes_encrypt(plaintext_json: str, hash_key: str, hash_iv: str) -> str:
    """AES-128-CBC 加密 — 完整實作見 guides/14 §Python"""
    url_encoded = ecticket_url_encode(plaintext_json)
    key = hash_key[:16].encode('utf-8')
    iv = hash_iv[:16].encode('utf-8')
    cipher = AES.new(key, AES.MODE_CBC, iv)
    padded = pad(url_encoded.encode('utf-8'), AES.block_size)
    return base64.b64encode(cipher.encrypt(padded)).decode('utf-8')

def calc_ecticket_cmv(data_plaintext: str, hash_key: str, hash_iv: str) -> str:
    """電子票證 CheckMacValue（與 AIO 金流不同！）"""
    raw = hash_key + data_plaintext + hash_iv
    url_encoded = ecticket_url_encode(raw).lower()
    return hashlib.sha256(url_encoded.encode('utf-8')).hexdigest().upper()

def issue_ticket():
    """票券發行（價金保管 — 使用後核銷，IssueType=4 純序號範例）"""
    data = {
        'MerchantID': MERCHANT_ID,
        'PaymentProvider': '2',       # 必填：1=綠界金流 2=其他
        'IssueType': '4',             # 必填：4=純序號（不需先上架商品）
        'Operator': 'System',         # 必填：建立人員（限英數字）
        'UseStatusNotifyURL': 'https://你的網站/ecticket/notify',
        'TicketInfo': [
            {
                'ItemName': '遊樂園入場券',  # IssueType=4 時必填
                'TicketPrice': 500,           # IssueType=4 時必填
                'TicketAmount': 1,            # 必填：發行張數
            },
        ],
    }
    data_json = json.dumps(data, separators=(',', ':'))  # ensure_ascii=True (預設) 與 PHP json_encode 預設行為一致

    payload = {
        'MerchantID': MERCHANT_ID,
        'RqHeader': {'Timestamp': int(time.time())},  # ECTicket RqHeader 僅需 Timestamp
        'Data': aes_encrypt(data_json, HASH_KEY, HASH_IV),
        'CheckMacValue': calc_ecticket_cmv(data_json, HASH_KEY, HASH_IV),
    }
    resp = requests.post(f'{BASE_URL}/api/issuance/issue', json=payload)
    result = resp.json()

    # 三重驗證
    if result.get('TransCode') != 1:
        raise Exception(f"傳輸層錯誤: {result.get('TransMsg')}")
    # 驗證回應 CheckMacValue → 解密 Data → 檢查 RtnCode
    return result

def write_off_ticket(write_off_no: str):
    """票券核銷"""
    data = {
        'MerchantID': MERCHANT_ID,
        'WriteOffNo': write_off_no,   # 必填：掃描票券 Barcode 取得的核銷代碼
        'Action': '1',                # 必填：1=核銷 2=取消核銷
        'Operator': 'System',         # 必填：操作人員（限英數字）
    }
    data_json = json.dumps(data, separators=(',', ':'))  # ensure_ascii=True (預設) 與 PHP json_encode 預設行為一致

    payload = {
        'MerchantID': MERCHANT_ID,
        'RqHeader': {'Timestamp': int(time.time())},  # ECTicket RqHeader 僅需 Timestamp
        'Data': aes_encrypt(data_json, HASH_KEY, HASH_IV),
        'CheckMacValue': calc_ecticket_cmv(data_json, HASH_KEY, HASH_IV),
    }
    resp = requests.post(f'{BASE_URL}/api/Ticket/WriteOff', json=payload)
    result = resp.json()

    if result.get('TransCode') != 1:
        raise Exception(f"傳輸層錯誤: {result.get('TransMsg')}")
    return result
```

> 依賴安裝：`pip install requests pycryptodome`
> 完整 AES 解密（含回應驗證）見 [guides/14 §Python](./14-aes-encryption.md)。

## 整合提示

1. **建議從「價金保管 — 使用後核銷」開始**，流程最直覺、風險最低
2. 如需多次使用票券，再評估「分期核銷」模式
3. 純發行適合已有自己金流管道的商家
4. 票券 QR Code 建議設定合理的有效期限
5. 核銷時注意防重複核銷（同一張票券不能核銷兩次）
6. 建議實作退票流程，提升消費者體驗

## API 規格索引

| 模式 | 文件 | URL 數量 |
|------|------|---------|
| 價金保管 — 使用後核銷 | `references/Ecticket/價金保管-使用後核銷API技術文件.md` | 21 |
| 價金保管 — 分期核銷 | `references/Ecticket/價金保管-分期核銷API技術文件.md` | 12 |
| 純發行 — 使用後核銷 | `references/Ecticket/純發行-使用後核銷API技術文件.md` | 24 |

## 三種模式的 API 功能對照

端點來源：官方 API 技術文件

| 功能 | 價金保管（使用後核銷） | 價金保管（分期核銷） | 純發行 |
|------|---------------------|-------------------|--------|
| 票券發行 `/api/issuance/issue`¹ | 有 | 無（後台管理） | 有 |
| 票券核銷 `/api/Ticket/WriteOff` | 有（一次性） | 無（後台管理） | 有（一次性） |
| 票券退貨 `/api/issuance/Refund`¹ | 有 | 有（訂單退貨） | 有 |
| 查詢履約保障天期 | 有 | 有 | 有 |
| 查詢商品資訊 | 有 | 無 | 有 |
| 批次查詢商品資訊 | 有 | 無 | 有 |
| 查詢票券發行結果 | 有 | 無 | 有 |
| 取得紙本票面資料 | 有 | 無 | 有 |
| 查詢票券明細 | 有 | 無 | 有 |
| 查詢訂單退款資訊 | 無 | 有 | 有 |
| 查詢訂單資訊 | 有 | 有 | 有 |
| 下載訂單明細檔 | 有 | 有 | 有 |
| 退款主動通知 | 無 | 有 | 有 |
| 核退主動通知 | 有 | 無 | 有 |

> ¹ 價金保管模式使用 `/api/issuance/` 路徑（發行 `/api/issuance/issue`、退貨 `/api/issuance/Refund`）；純發行模式使用 `/api/Ticket/Issue` 和 `/api/Ticket/Refund`。

> 所有電子票證 API 使用 AES 三層結構 + CheckMacValue（SHA256），
> AES 加解密方式與 B2C 發票相同（參考 [guides/14-aes-encryption.md](./14-aes-encryption.md)），
> 但 CheckMacValue 為電子票證獨有，計算方式見上方 §CheckMacValue 計算

> ⚠️ **安全必做清單（UseStatusNotifyURL / 退款通知）**
> 1. 驗證 MerchantID 為自己的
> 2. **驗證 CheckMacValue**（Callback 使用 **ECTicket 公式** `key+Data明文JSON+iv`，見本文 §CheckMacValue 計算 情境 B），且**必須**使用 timing-safe 比較函式（見 [guides/13](./13-checkmacvalue.md) 各語言實作），禁止使用 `==` 或 `===` 直接比對
> 3. 防重複處理（記錄已處理的票券編號）
> 4. 回應 AES 加密 JSON 三層結構（Data 內 `{"RtnCode": 1, "RtnMsg": "成功"}`），否則每 5-15 分鐘重送，每日最多 4 次
> 5. 記錄完整日誌（遮蔽 HashKey/HashIV）

## 相關文件

- AES 加解密：[guides/14-aes-encryption.md](./14-aes-encryption.md)
- CheckMacValue（AIO 金流版）：[guides/13-checkmacvalue.md](./13-checkmacvalue.md)（注意：電子票證的 CMV 計算公式不同，見本文 §CheckMacValue 計算）
- 金流串接（搭配票券銷售）：[guides/01-payment-aio.md](./01-payment-aio.md)
- 站內付 2.0（嵌入式收款）：[guides/02-payment-ecpg.md](./02-payment-ecpg.md)
- 電子發票（搭配開立）：[guides/04-invoice-b2c.md](./04-invoice-b2c.md)
- 跨服務整合場景：[guides/11-cross-service-scenarios.md](./11-cross-service-scenarios.md)
- 除錯指南：[guides/15-troubleshooting.md](./15-troubleshooting.md)
- 上線檢查：[guides/16-go-live-checklist.md](./16-go-live-checklist.md)
