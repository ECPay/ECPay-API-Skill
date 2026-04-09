> 對應 ECPay API 版本 | 基於 PHP SDK ecpay/sdk | 最後更新：2026-03

<!-- AI Section Index (guides/02 hub, 1359 lines，2026-03-25 校準)
子指南（獨立文件，行號不適用於本文）: 02a 首次串接, 02b ATM/SPA, 02c App/正式環境
以下行號皆為本文件（guides/02）內的行號：
Domain 警告 + AI 生成代碼必讀: line 36-97
概述: line 98-159
站內付2.0 vs AIO 差異: line 160-171
前置需求: line 172-177
HTTP 協議速查: line 178-218
AES 三層請求結構: line 219-236
非 PHP 語言整合指引: line 237-426
一般付款流程（GetToken / CreatePayment / 回應處理）: line 427-946
綁卡付款流程: line 947-1043
會員綁卡管理: line 1044-1104
請款 / 退款: line 1105-1131
定期定額管理: line 1132-1152
查詢（一般 / 信用卡 / 付款資訊 / 定期定額）: line 1153-1189
對帳: line 1190-1230
安全注意事項: line 1231-1304
AI 生成代碼常見錯誤: line 1305-1328
完整範例檔案對照: line 1329-1359
-->

# 站內付 2.0 完整指南

> ## 你應該讀哪個文件？
>
> | 你的情況 | 讀哪個 | 預估時間 |
> |---------|-------|---------|
> | **首次串接站內付 2.0** | **→ [guides/02a](./02a-ecpg-quickstart.md)**（5 步驟 + 可執行範例） | 25 分鐘 |
> | ATM / CVS / SPA 整合 | → [guides/02b](./02b-ecpg-atm-cvs-spa.md)（非信用卡付款特殊流程） | 10 分鐘 |
> | App 整合 / Apple Pay / 正式環境 | → [guides/02c](./02c-ecpg-app-production.md) | 10 分鐘 |
> | 完整技術參考（綁卡、查詢、定期定額、對帳）| 本文（guides/02）| 60 分鐘 |

> ⚠️ **站內付2.0 使用兩個不同 Domain — 打錯立得 HTTP 404**
>
> | API 類別 | 測試 Domain | 正式 Domain |
> |---------|------------|------------|
> | Token 取得 API（**注意**：CreatePayment 同屬此 domain） | `ecpg-stage.ecpay.com.tw` | `ecpg.ecpay.com.tw` |
> | 查詢 / 請款 / 退款 API | `ecpayment-stage.ecpay.com.tw` | `ecpayment.ecpay.com.tw` |
>
> 先確認 Domain 再開始撰寫程式碼。

> 📌 **首次串接站內付 2.0？** 建議先讀 [guides/02a — 首次串接快速路徑](./02a-ecpg-quickstart.md)（含 Python / Node.js 完整範例，約 25 分鐘可完成首筆測試交易），再回到本文件查閱進階主題。
> ATM / CVS / SPA 整合 → [guides/02b](./02b-ecpg-atm-cvs-spa.md) | App / Apple Pay / 正式環境 → [guides/02c](./02c-ecpg-app-production.md)

> **⚠️ AI 生成代碼必讀（非 PHP 語言）**
>
> 生成任何非 PHP 語言的站內付 2.0 代碼前，**必須同時載入 [guides/14-aes-encryption.md](./14-aes-encryption.md)**，
> 否則無法正確實作 AES 加解密。以下是最常導致生成失敗的細節：
>
> **⓪ ConsumerInfo — GetTokenbyTrade 必填物件（最常見的複製失敗根因）**
>
> | 狀況 | 欄位 |
> |------|------|
> | **RememberCard=0**（一次付款）| ConsumerInfo 物件必傳，Email **或** Phone 擇一必填 |
> | **RememberCard=1**（記憶卡號）| ConsumerInfo 物件必傳，且 MerchantMemberID 也必填 |
>
> ```python
> 'ConsumerInfo': {
>     'MerchantMemberID': 'member001',  # RememberCard=1 時必填；=0 時可省略
>     'Email':  'customer@example.com', # Email 或 Phone 擇一必填
>     'Phone':  '0912345678',
>     'Name':   '顧客',
>     'CountryCode': '158',
> }
> ```
>
> > ❗ **省略 ConsumerInfo 或省略 Email/Phone → GetToken 回傳 RtnCode ≠ 1，無明確錯誤訊息。**
>
> **① Domain 路由（每個 API 對應不同 domain）**
>
> | API 操作 | 必須使用的 Domain |
> |---------|-----------------|
> | GetTokenbyTrade / GetTokenbyUser / CreatePayment / 所有綁卡 API | **ecpg**(-stage).ecpay.com.tw |
> | QueryTrade / DoAction / CreditCardPeriodAction / QueryPaymentInfo / QueryTradeMedia | **ecpayment**(-stage).ecpay.com.tw |
>
> **② 生成代碼時必須同時遵守的 5 個規則**
>
> | 規則 | 正確做法 |
> |------|---------|
> | Timestamp 單位 | **Unix 秒**（`Math.floor(Date.now()/1000)`），不是毫秒 |
> | MerchantID 位置 | **外層和 Data 內層各一份**，兩處都必須填寫，不可只填一處 |
> | RqHeader 欄位 | 站內付 2.0 的 RqHeader **只有 `Timestamp`**，無 `Revision` 欄位。⚠️ 跨服務差異：B2C 發票需 `Revision: "3.0.0"`、全方位物流需 `"1.0.0"`，從其他服務複製程式碼時務必移除 Revision |
> | Data 欄位加密 | PHP SDK 的 `PostWithAesJsonResponseService` 會自動加密 Data。非 PHP 必須手動做：`json_encode → urlencode → AES-128-CBC 加密 → base64_encode`（詳見 [guides/14](./14-aes-encryption.md)） |
> | AES URL Encode | 站內付使用 **`aesUrlEncode`**（只做 `urlencode`，不做 lowercase 和 .NET 替換），絕對不可使用 CheckMacValue 的 `ecpayUrlEncode` |
>
> **③ ThreeDURL — 2025/8 起幾乎所有信用卡交易都需要處理**
>
> CreatePayment 回應的 Data 解密後，若含 `ThreeDURL` 欄位（非空字串），**必須將用戶導向該 URL** 完成 3D 驗證。忘記此分支會導致交易逾時失敗。生成代碼時此分支不可省略。
>
> **④ Callback 回應格式**
>
> - **ReturnURL**（Server-to-Server, Content-Type: application/json）：讀取 JSON body → 回應純文字 `1|OK`（嚴格 4 個字元，**不含尾隨換行或空白**）
> - **OrderResultURL**（瀏覽器 Form POST, Content-Type: application/x-www-form-urlencoded）：讀取 `ResultData` 表單欄位（**JSON 字串**，非直接 AES 加密）→ JSON 解析取外層 `{TransCode, Data}` → AES 解密 `Data` → 顯示結果頁面

## 概述

站內付 2.0 是 ECPG 最常使用的服務，讓付款體驗嵌入你自己的頁面，消費者不需要跳轉到綠界。使用 AES 加密和 JSON 格式。適合需要自訂付款 UI 或綁卡功能的場景。

### 何時選擇站內付 2.0？

1. **嵌入式支付表單** — 不想讓消費者跳轉到綠界付款頁面
2. **前後端分離架構（React/Vue/Angular/SPA）** — 需要 API 模式而非 Form POST
3. **綁卡與定期定額** — 需要完整的 Token 管理
4. **App 支付** — iOS/Android 原生付款體驗（含 Apple Pay）

> 若只是簡單線上收款，**AIO（[guides/01](./01-payment-aio.md)）更簡單**，30 分鐘即可完成串接。

> **只做 Web 整合？** 直接跳到 [一般付款流程](#一般付款流程)。
> **只做 App 整合？** 直接跳到 [Web vs App 整合差異](#web-vs-app-整合差異)。

> **⚠️⚠️⚠️ 站內付2.0 最常見錯誤：Domain 打錯 = 404**
>
> 站內付2.0 使用**兩個不同的 domain**，搞混必定 404：
>
> | 用途 | Domain | 端點範例 |
> |------|--------|---------|
> | Token / 建立交易 | **ecpg**(-stage).ecpay.com.tw | GetTokenbyTrade, CreatePayment |
> | 查詢 / 請退款 | **ecpayment**(-stage).ecpay.com.tw | QueryTrade, DoAction |
>
> **錯誤範例**：把 QueryTrade 打到 `ecpg.ecpay.com.tw` → 404
> **正確做法**：對照下方[端點 URL 一覽](#端點-url-一覽)確認每個 API 的 domain

### 內部導航

| 區塊 | 說明 |
|------|------|
| [**🚀 首次串接快速路徑**](./02a-ecpg-quickstart.md) | **⭐ 新手從這裡開始 — 5 步驟分段驗證流程** → 02a |
| [**⚡ 完整可執行範例（信用卡 / ATM / CVS）**](./02a-ecpg-quickstart.md#-完整可執行範例pythonnodejs) | **複製即可跑 — Python Flask + Node.js Express** → 02a |
| [**⚠️ ATM / CVS 首次串接快速路徑**](./02b-ecpg-atm-cvs-spa.md) | **ATM/CVS 開發者從這裡看 GetToken 參數差異** → 02b |
| [**⚠️ 非信用卡 Callback 時序**](./02b-ecpg-atm-cvs-spa.md#非信用卡付款atm--cvs--barcode-的-callback-時序) | **ATM/CVS ReturnURL 非同步說明** → 02b |
| [**🖥️ SPA / React / Vue / Next.js**](./02b-ecpg-atm-cvs-spa.md#-spa--react--vue--nextjs-整合架構) | **前後端分離架構陷阱** → 02b |
| [站內付2.0 vs AIO 差異](#站內付20-vs-aio-差異) | 選型比較 |
| [HTTP 協議速查](#http-協議速查非-php-語言必讀) | 端點、加密、請求格式 |
| [**非 PHP 語言整合指引**](#非-php-語言整合指引) | **⚠️ 非 PHP 必讀 — PHP SDK 做了什麼、需自行實作什麼** |
| [一般付款流程](#一般付款流程) | GetToken → CreatePayment → 處理回應 |
| [前端 JavaScript SDK 整合](#前端-javascript-sdk-整合) | JS SDK 嵌入付款表單 |
| [綁卡付款流程](#綁卡付款流程) | Token 綁定 + 扣款 |
| [會員綁卡管理](#會員綁卡管理) | 查詢/刪除綁卡 |
| [請款 / 退款](#請款--退款) | DoAction 操作 |
| [定期定額管理](#定期定額管理) | 訂閱扣款管理 |
| [查詢](#查詢) | 訂單/信用卡/付款資訊/定期定額查詢 |
| [對帳](#對帳) | 對帳檔下載 |
| [Web vs App 整合差異](./02c-ecpg-app-production.md) | iOS/Android 原生 SDK + WebView → 02c |
| [安全注意事項](#安全注意事項) | GetResponse 安全處理 |
| [**Apple Pay 整合前置準備**](./02c-ecpg-app-production.md#apple-pay-整合前置準備) | **域名驗證、Merchant ID、憑證** → 02c |
| [正式環境實作注意事項](./02c-ecpg-app-production.md#正式環境實作注意事項) | Token 刷新、冪等性 → 02c |
| [正式環境切換清單](./02c-ecpg-app-production.md#正式環境切換清單) | 測試→正式 Checklist → 02c |
| [**AI 生成代碼常見錯誤**](#ai-生成代碼常見錯誤) | **⚠️ 生成非 PHP 代碼前必讀 — 12 個高頻錯誤** |


> 📖 **首次串接快速路徑**（5 步驟分段驗證 + Python/Node.js 完整可執行範例）已移至 [02a-ecpg-quickstart.md](./02a-ecpg-quickstart.md)

> 📖 **ATM/CVS 快速路徑 + SPA 整合架構**（ATM/CVS 首次串接 + 非信用卡 Callback 時序 + React/Vue/Next.js）已移至 [02b-ecpg-atm-cvs-spa.md](./02b-ecpg-atm-cvs-spa.md)

---

## 站內付2.0 vs AIO 差異

| 面向 | AIO | 站內付2.0 |
|------|-----|------|
| 付款頁面 | 導向綠界頁面 | 嵌入你的頁面 |
| 加密方式 | CheckMacValue (SHA256) | AES-128-CBC |
| 請求格式 | Form POST (URL-encoded) | JSON POST |
| 請求結構 | 扁平 key=value | 三層：MerchantID + RqHeader + Data |
| 綁卡功能 | 有限 | 完整（Token 綁定） |
| 前後端分離 | 不需要 | 前端取 Token → 後端建立交易 |
| App 整合 | 無 | 支援（原生 SDK 取 Token） |

## 前置需求

- MerchantID / HashKey / HashIV（測試：3002607 / pwFHCqoQZGmho4w6 / EkRm7iFT261dpevs）
- PHP SDK：`composer require ecpay/sdk`
- SDK Service：`PostWithAesJsonResponseService`

## HTTP 協議速查（非 PHP 語言必讀）

| 項目 | 規格 |
|------|------|
| 協議模式 | AES-JSON — 詳見 [guides/20-http-protocol-reference.md](./20-http-protocol-reference.md) |
| HTTP 方法 | POST |
| Content-Type | `application/json` |
| 認證 | AES-128-CBC 加密 Data 欄位 — 詳見 [guides/14-aes-encryption.md](./14-aes-encryption.md) |
| Token 環境 | `https://ecpg-stage.ecpay.com.tw`（測試） / `https://ecpg.ecpay.com.tw`（正式） |
| 交易/查詢環境 | `https://ecpayment-stage.ecpay.com.tw`（測試） / `https://ecpayment.ecpay.com.tw`（正式） |
| 回應結構 | 三層 JSON（TransCode → 解密 Data → RtnCode） |
| Callback 回應 | `1\|OK`（官方規格 9058.md） |

> **注意**：站內付2.0 使用**兩個不同 domain** — Token 相關（GetTokenbyTrade/GetTokenbyUser/CreatePayment）走 `ecpg`，查詢/請退款走 `ecpayment`。詳見 [guides/20 站內付2.0 端點表](./20-http-protocol-reference.md)。

> ⚠️ **SNAPSHOT 2026-03** | 來源：`references/Payment/站內付2.0API技術文件Web.md` 及 `references/Payment/站內付2.0API技術文件App.md`
> 以下端點及參數僅供整合流程理解，不可直接作為程式碼生成依據。**生成程式碼前必須 web_fetch 來源文件取得最新規格。**

### 端點 URL 一覽

| 功能 | 端點路徑 | Base Domain |
|------|---------|------------|
| **── Token / 建立交易（ecpg domain）──** | | |
| 以交易取 Token | `/Merchant/GetTokenbyTrade` | **ecpg** |
| 以會員取 Token | `/Merchant/GetTokenbyUser` | **ecpg** |
| 建立交易 | `/Merchant/CreatePayment` | **ecpg** |
| 綁卡取 Token | `/Merchant/GetTokenbyBindingCard` | **ecpg** |
| 建立綁卡 | `/Merchant/CreateBindCard` | **ecpg** |
| 以卡號付款 | `/Merchant/CreatePaymentWithCardID` | **ecpg** |
| 查詢會員綁卡 | `/Merchant/GetMemberBindCard` | **ecpg** |
| 刪除會員綁卡 | `/Merchant/DeleteMemberBindCard` | **ecpg** |
| ⚠️ *上方 5 個綁卡端點尚無獨立官方文件 URL，參數規格以 SDK 範例為準* | | |
| **── 查詢 / 請退款（ecpayment domain）──** | | |
| 信用卡請退款 | `/1.0.0/Credit/DoAction` | **ecpayment** |
| 查詢訂單 | `/1.0.0/Cashier/QueryTrade` | **ecpayment** |
| 信用卡明細查詢 | `/1.0.0/CreditDetail/QueryTrade` | **ecpayment** |
| 定期定額查詢 | `/1.0.0/Cashier/QueryTrade` | **ecpayment** |
| 定期定額作業 | `/1.0.0/Cashier/CreditCardPeriodAction` | **ecpayment** |
| 取號結果查詢 | `/1.0.0/Cashier/QueryPaymentInfo` | **ecpayment** |
| 下載撥款對帳檔 | `/1.0.0/Cashier/QueryTradeMedia` | **ecpayment** |

## AES 三層請求結構

所有站內付2.0 API 都使用相同的外層結構：

```json
{
  "MerchantID": "3002607",
  "RqHeader": {
    "Timestamp": 1234567890
  },
  "Data": "AES加密後的Base64字串"
}
```

Data 欄位的加解密流程：見 [guides/14-aes-encryption.md](./14-aes-encryption.md)

> **注意**：`Timestamp` 為 **Unix 秒**（整數），不是毫秒。`RqHeader` 站內付2.0 **只有 `Timestamp` 一個欄位**，不要加 `Revision`（那是發票/物流 API 才有的欄位）。

## 非 PHP 語言整合指引

> **⚠️ 生成非 PHP 代碼時必讀。** PHP SDK 的 `PostWithAesJsonResponseService` 在一次 `post()` 呼叫中自動完成以下所有事情。非 PHP 語言**必須手動實作**每一步，漏掉任何一步都會導致 ECPay 端解密失敗或回傳 TransCode ≠ 1。

### PHP SDK 自動處理的步驟（非 PHP 必須手動實作）

```
請求端（每次呼叫 post() 自動執行）：
  ① $data 陣列 → json_encode()                → JSON 字串
  ② JSON 字串  → urlencode()                  → URL 編碼字串（注意：不是 ecpayUrlEncode，只是純 urlencode）
  ③ URL 編碼   → openssl_encrypt(AES-128-CBC)  → 二進位密文（含 PKCS7 padding）
  ④ 密文       → base64_encode()              → Base64 字串（標準 alphabet，含 +/=）
  ⑤ 組合外層   → { MerchantID, RqHeader: {Timestamp}, Data: <④的結果> }
  ⑥ 外層 JSON  → HTTP POST（Content-Type: application/json）→ ECPay

回應端（post() 回傳前自動執行）：
  ⑦ HTTP 回應 body → json_decode() → 取得外層 { TransCode, Data }
  ⑧ Data 字串 → base64_decode()               → 二進位密文
  ⑨ 密文       → openssl_decrypt(AES-128-CBC)  → URL 編碼字串
  ⑩ URL 編碼   → urldecode()                  → JSON 字串
  ⑪ JSON 字串  → json_decode()                → 最終業務資料陣列
```

### 非 PHP 語言實作對照

| 步驟 | Python | Node.js / TypeScript | Go | Java / Kotlin |
|------|--------|---------------------|-----|--------------|
| JSON 序列化 | `json.dumps()` | `JSON.stringify()` | `json.Marshal()` | `new ObjectMapper().writeValueAsString()` |
| URL Encode（AES 用） | `urllib.parse.quote_plus()` + `replace('~','%7E')` | `encodeURIComponent()` + `replace(/%20/g,'+')` + `replace(/~/g,'%7E')` | `url.QueryEscape()` | `URLEncoder.encode(s,"UTF-8")` |
| AES-128-CBC 加密 | `Crypto.AES.MODE_CBC` + PKCS7 pad | `crypto.createCipheriv('aes-128-cbc')` | `aes.NewCipher()` + `cipher.NewCBCEncrypter()` | `Cipher.getInstance("AES/CBC/PKCS5Padding")` |
| Base64 | `base64.b64encode()` | `Buffer.from(x).toString('base64')` | `base64.StdEncoding.EncodeToString()` | `Base64.getEncoder().encodeToString()` |
| HTTP POST | `requests.post(url, json=body)` | `fetch(url, {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body)})` | `http.Post()` | `HttpClient` |

**各語言完整實作代碼** → [guides/14 AES 加解密](./14-aes-encryption.md)（含 12 種語言完整函式）

### 非 PHP 請求組裝範例（Python）

```python
import json, time, base64, urllib.parse
from Crypto.Cipher import AES
from Crypto.Util.Padding import pad

HASH_KEY = b'pwFHCqoQZGmho4w6'  # 必須是 16 bytes
HASH_IV  = b'EkRm7iFT261dpevs'  # 必須是 16 bytes

def aes_encrypt(data: dict) -> str:
    """guides/14 aesUrlEncode + AES-128-CBC + base64"""
    json_str = json.dumps(data, ensure_ascii=False, separators=(',', ':'))
    # aesUrlEncode：quote_plus + ~替換（不做 lowercase 和 .NET 替換）
    url_encoded = urllib.parse.quote_plus(json_str).replace('~', '%7E')
    padded = pad(url_encoded.encode('utf-8'), 16)  # PKCS7
    cipher = AES.new(HASH_KEY, AES.MODE_CBC, HASH_IV)
    return base64.b64encode(cipher.encrypt(padded)).decode('utf-8')

def build_request(merchant_id: str, data: dict) -> dict:
    return {
        "MerchantID": merchant_id,                  # 外層 MerchantID
        "RqHeader": {"Timestamp": int(time.time())}, # Unix 秒，不是毫秒
        "Data": aes_encrypt(data)                    # data 內也要有 MerchantID
    }

# GetToken 請求範例
payload = build_request("3002607", {
    "MerchantID": "3002607",   # Data 內的 MerchantID（必填，不可省略）
    "RememberCard": 1,
    "PaymentUIType": 2,
    "ChoosePaymentList": "1",
    "OrderInfo": {
        "MerchantTradeDate": "2026/03/12 10:00:00",
        "MerchantTradeNo": f"test{int(time.time())}",
        "TotalAmount": 100,     # 整數
        "ReturnURL": "https://yourdomain.com/ecpay/notify",
        "TradeDesc": "測試",
        "ItemName": "商品"
    },
    "CardInfo": {"OrderResultURL": "https://yourdomain.com/ecpay/result"},
    "ConsumerInfo": {
        "MerchantMemberID": "member001",
        "Email": "test@example.com",
        "Phone": "0912345678",
        "Name": "測試",
        "CountryCode": "158"
    }
})
```

**Python 步驟 4：建立交易（CreatePayment）**

```python
import json, time, base64, urllib.parse, requests
from Crypto.Cipher import AES
from Crypto.Util.Padding import pad, unpad

HASH_KEY = b'pwFHCqoQZGmho4w6'
HASH_IV  = b'EkRm7iFT261dpevs'

def aes_decrypt(encrypted_base64: str) -> dict:
    raw = base64.b64decode(encrypted_base64)
    cipher = AES.new(HASH_KEY, AES.MODE_CBC, HASH_IV)
    decrypted = unpad(cipher.decrypt(raw), 16).decode('utf-8')
    return json.loads(urllib.parse.unquote_plus(decrypted))

def create_payment(pay_token: str, merchant_trade_no: str, total: int = None) -> dict:
    """步驟 4：傳入步驟 3 的 PayToken 與步驟 1 相同的 MerchantTradeNo
    total: Apple Pay 延遲付款（PaymentUIType=5）時必填，為最終授權金額（Int，不可為 0）
    """
    data_payload = {
        "MerchantID": "3002607",        # Data 內也必須有 MerchantID
        "PayToken": pay_token,
        "MerchantTradeNo": merchant_trade_no,  # ← 必須與 GetTokenbyTrade 完全相同
    }
    if total is not None:
        data_payload["Total"] = total   # ⚠️ Apple Pay 延遲付款（PaymentUIType=5）必填
    body = {
        "MerchantID": "3002607",
        "RqHeader": {"Timestamp": int(time.time())},
        "Data": aes_encrypt(data_payload)   # 複用步驟 0 / 步驟 1 的 aes_encrypt
    }
    resp = requests.post(
        'https://ecpg-stage.ecpay.com.tw/Merchant/CreatePayment',  # ← ecpg，不是 ecpayment
        json=body
    )
    outer = resp.json()
    if outer.get('TransCode') != 1:
        raise RuntimeError(f"傳輸層錯誤: {outer.get('TransMsg')}")
    return aes_decrypt(outer['Data'])   # 回傳解密後的業務資料

# 呼叫範例
data = create_payment(pay_token='步驟3取得的PayToken', merchant_trade_no='步驟1使用的訂單編號')

# ⚠️ 官方規格（9053.md）回應為巢狀結構：ThreeDInfo.ThreeDURL、OrderInfo.TradeNo 等
# ⚠️ 必須先判斷 ThreeDURL（2025/8 後幾乎必定出現）
three_d_url = data.get('ThreeDInfo', {}).get('ThreeDURL', '')
if three_d_url:
    # 將 ThreeDURL 傳給前端，前端執行跳轉
    # return jsonify({"threeDUrl": three_d_url})
    print(f"前端必須跳轉至: {three_d_url}")
elif data.get('RtnCode') == 1:
    # 不需 3D 驗證，交易直接成功
    print(f"交易成功，TradeNo: {data.get('OrderInfo', {}).get('TradeNo')}")
else:
    # ThreeDURL 為空且 RtnCode != 1 才是真正失敗
    print(f"授權失敗: {data.get('RtnMsg')}")
```

**Python 步驟 5：接收 Callback（Flask）**

> 參見步驟 5（前方）的 Flask 範例，`aes_decrypt` 函式與本節完全相同，可直接複用。

```python
# Flask ReturnURL + OrderResultURL 完整範例（複用上方的 aes_decrypt）
from flask import Flask, request
import hmac  # 用於 timing-safe 比較（若有需要驗證 MerchantTradeNo）

app = Flask(__name__)

@app.route('/ecpay/notify', methods=['POST'])
def ecpay_notify():
    """ReturnURL — 綠界伺服器發送，Content-Type: application/json"""
    body = request.get_json()
    if not body or body.get('TransCode') != 1:
        return '1|OK', 200, {'Content-Type': 'text/plain'}
    data = aes_decrypt(body['Data'])
    if data.get('RtnCode') == 1:
        # ⚠️ 官方規格（9058.md）回應為巢狀結構：訂單資訊在 OrderInfo 物件內
        order_info = data.get('OrderInfo', {})
        pass  # TODO: 更新訂單 update_order(order_info.get('MerchantTradeNo'), 'paid')
    return '1|OK', 200, {'Content-Type': 'text/plain'}  # ← 必須：純文字，HTTP 200

@app.route('/ecpay/result', methods=['POST'])
def ecpay_result():
    """OrderResultURL — 消費者瀏覽器發送，Content-Type: application/x-www-form-urlencoded"""
    result_data = request.form.get('ResultData', '')  # ⚠️ 表單欄位，不是 JSON body
    if not result_data:
        return '<h1>資料接收失敗</h1>'
    # ⚠️ ResultData 是 JSON 字串，需先 json.loads 取外層，再 AES 解密 Data 欄位
    outer = json.loads(result_data)          # ← Step 1：JSON 解析外層 {TransCode, Data}
    if outer.get('TransCode') != 1:
        return '<h1>資料傳輸錯誤</h1>'
    data = aes_decrypt(outer['Data'])        # ← Step 2：AES 解密 Data 欄位
    # ⚠️ 官方規格（15076.md）回應為巢狀結構：訂單資訊在 OrderInfo 物件內
    if data.get('RtnCode') == 1:
        return f"<h1>付款成功！訂單：{data.get('OrderInfo', {}).get('MerchantTradeNo')}</h1>"
    return f"<h1>付款失敗：{data.get('RtnMsg', '未知錯誤')}</h1>"
    # ← 不需回應 1|OK，顯示結果頁面給消費者即可

if __name__ == '__main__':
    app.run(port=3000)
```

## 一般付款流程

### 步驟 1：前端取得 Token

前端根據付款方式，呼叫不同的 GetToken API 取得 `PayToken`。

#### 8 種付款方式的 GetToken 差異

> 原始範例：`scripts/SDK_PHP/example/Payment/Ecpg/Create*Order/GetToken.php`

| 付款方式 | ChoosePaymentList | 專用參數物件 | 範例檔案 |
|---------|------------------|-------------|---------|
| 全部 | "0" | CardInfo + UnionPayInfo + ATMInfo + CVSInfo + BarcodeInfo | CreateAllOrder/GetToken.php |
| 信用卡 | "1" | CardInfo（Redeem, OrderResultURL） | CreateCreditOrder/GetToken.php |
| 分期 | "2,8" | CardInfo（CreditInstallment, FlexibleInstallment） | CreateInstallmentOrder/GetToken.php |
| ATM | "3" | ATMInfo（ExpireDate） | CreateAtmOrder/GetToken.php |
| 超商代碼 | "4" | CVSInfo（StoreExpireDate） | CreateCvsOrder/GetToken.php |
| 條碼 | "5" | BarcodeInfo（StoreExpireDate） | CreateBarcodeOrder/GetToken.php |
| 銀聯 | "6" | UnionPayInfo（OrderResultURL） | CreateUnionPayOrder/GetToken.php |
| Apple Pay | "7" | （無額外參數） | CreateApplePayOrder/GetToken.php |

**端點**：`POST https://ecpg-stage.ecpay.com.tw/Merchant/GetTokenbyTrade`

**完整 GetToken 請求**（以全方位為例）：

```php
$postService = $factory->create('PostWithAesJsonResponseService');
$input = [
    'MerchantID' => '3002607',
    'RqHeader'   => ['Timestamp' => time()],
    'Data'       => [
        'MerchantID'       => '3002607',
        'RememberCard'     => 1,
        'PaymentUIType'    => 2,
        'ChoosePaymentList'=> '0',
        'OrderInfo' => [
            'MerchantTradeDate' => date('Y/m/d H:i:s'),
            'MerchantTradeNo'   => 'Test' . time(),
            'TotalAmount'       => 100,
            'ReturnURL'         => 'https://你的網站/ecpay/notify',
            'TradeDesc'         => '測試交易',
            'ItemName'          => '測試商品',
        ],
        'CardInfo' => [
            'Redeem'            => 0,  // ⚠️ 整數 0（PHP SDK 寫法），非 PHP 語言建議省略此欄位
            'OrderResultURL'    => 'https://你的網站/ecpay/result',
            'CreditInstallment' => '3,6,12',
        ],
        'ATMInfo'     => ['ExpireDate' => 3],
        'CVSInfo'     => ['StoreExpireDate' => 10080],
        'BarcodeInfo' => ['StoreExpireDate' => 7],
        'ConsumerInfo'=> [
            'MerchantMemberID' => 'member001',
            'Email'  => 'test@example.com',
            'Phone'  => '0912345678',
            'Name'   => '測試',
            'CountryCode' => '158',
        ],
    ],
];
try {
    $response = $postService->post($input, 'https://ecpg-stage.ecpay.com.tw/Merchant/GetTokenbyTrade');
    // 解密 Data 取得 Token
    $token = $response['Data']['Token'] ?? null;
    if (!$token) {
        error_log('站內付2.0 GetToken failed: ' . json_encode($response));
    }
} catch (\Exception $e) {
    error_log('站內付2.0 GetToken Error: ' . $e->getMessage());
}
```

回應的 Data 解密後包含 `Token`，傳給前端 JavaScript SDK 顯示付款介面。

> 🔍 **此步驟失敗？** TransCode ≠ 1 → 排查見 [§15 TransCode 診斷](./15-troubleshooting.md#15-站內付20-transcode--1-診斷流程)；HTTP 404 → 確認 Domain 為 `ecpg-stage.ecpay.com.tw`（非 `ecpayment`）。

### 前端 JavaScript SDK 整合

後端 GetToken 呼叫取得 Token 後，在前端使用 ECPay JavaScript SDK：

```
> 原始範例：scripts/SDK_PHP/example/Payment/Ecpg/CreateCreditOrder/WebJS.html
```

**1. 引入 ECPay JavaScript SDK**

> ⚠️ **三個依賴缺一不可**：ECPay JS SDK 依賴 jQuery 和 node-forge，必須在 SDK 之前載入，否則 SDK 會直接 throw Error。

```html
<!-- 1. jQuery（必要依賴，SDK 啟動時檢查 typeof jQuery） -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- 2. node-forge（必要依賴，SDK 用於前端加密） -->
<script src="https://cdn.jsdelivr.net/npm/node-forge@0.7.0/dist/forge.min.js"></script>
<!-- 3. ECPay 站內付 JS SDK -->
<!-- ⚠️ JS SDK 一律從正式 domain 載入（測試/正式都用同一個 URL）
     環境切換透過 ECPay.initialize('Stage'|'Prod', ...) 控制
     stage domain 的 SDK 檔案與正式版不同（大小不同、行為可能異常），不可使用 -->
<script src="https://ecpg.ecpay.com.tw/Scripts/sdk-1.0.0.js"></script>
```

> **⚠️ JS SDK domain 重要**：JS SDK **一律從正式 domain `ecpg.ecpay.com.tw` 載入**（與官方 WebJS.html 一致），
> 測試/正式環境切換透過 `ECPay.initialize('Stage'|'Prod', ...)` 控制。
> **不要使用 `ecpg-stage.ecpay.com.tw/Scripts/sdk-1.0.0.js`**——stage 版 SDK 是不同檔案（14.7KB vs 正式版 12.7KB），可能不完整或行為異常。
> 路徑為 `/Scripts/sdk-1.0.0.js`（大寫 `S`）。ECPay 可能更新 SDK 版本或路徑，
> 請以[綠界站內付官方文件](https://developers.ecpay.com.tw/)及[官方 GitHub 範例](https://github.com/ECPay/ECPayPaymentGatewayKit_Web)中的最新版本為準。

> **CSP（Content Security Policy）設定**：若你的網站啟用了 CSP header，需允許 ECPay domain：
> - `script-src`: 加入 `https://ecpg.ecpay.com.tw`
> - `frame-src`: 加入 `https://ecpg.ecpay.com.tw`（正式）及 `https://ecpg-stage.ecpay.com.tw`（測試，SDK 內部 iframe 連接測試環境）
> - `connect-src`: 加入 `https://ecpg.ecpay.com.tw`（正式）及 `https://ecpg-stage.ecpay.com.tw`（測試，SDK 內部 API 呼叫）

**2. 容器 div（固定 ID，不可更改）**

> ⚠️ **SDK 硬編碼渲染至 `id="ECPayPayment"` 的 div**，此 ID 不可自訂。官方範例註解：「請勿更動 id」。

```html
<!--渲染付款界面UI，請勿更動id-->
<div id="ECPayPayment"></div>
```

**3. 初始化 SDK**

> **API 呼叫風格說明**：下方使用官方 SDK 的 positional 風格（`ECPay.createPayment(Token, Language, callBack, Version)`）。
> 以[官方文件](https://developers.ecpay.com.tw/9003.md)及 [GitHub 範例](https://github.com/ECPay/ECPayPaymentGatewayKit_Web)為準。

```javascript
// envi: 'Stage'=測試, 'Prod'=正式（⚠️ 字串，非整數 0/1）
// type: 1=Web
// ⚠️ createPayment() 必須在 initialize() callback 內呼叫（官方 WebJS.html 寫法）
//    若寫在外面會形成競態條件：SDK 尚未初始化完成就嘗試渲染 → 永遠轉圈
ECPay.initialize('Stage', 1, function(errMsg) {
    if (errMsg != null) { console.error('SDK 初始化失敗:', errMsg); return; }
    // _token: 後端 GetTokenbyTrade 取得的 Token
    // language: 'zh-TW', 'en-US', etc.
    // SDK 自動渲染至 <div id="ECPayPayment">
    ECPay.createPayment(_token, language, function(errMsg) {
        if (errMsg != null) console.error('建立付款 UI 失敗:', errMsg);
    }, 'V2');
});
```

**4. 取得 PayToken（消費者填完付款資訊後）**
```javascript
// ⚠️ callback 第一個參數是物件（非字串），必須取 .PayToken 屬性
// ⚠️ errMsg 檢查必須用 != null（空字串 "" 是 falsy 但非無錯誤）
ECPay.getPayToken(function(paymentInfo, errMsg) {
    if (errMsg != null) {
        console.error('取得 PayToken 失敗:', errMsg);
        return;
    }
    // paymentInfo.PayToken 才是字串，不可直接送 paymentInfo 物件
    submitPayment(paymentInfo.PayToken);
});
```

> ⚠️ **常見陷阱：callback 第一個參數是物件，不是字串**
> `getPayToken` 回呼的第一個參數 `paymentInfo` 是**物件**，必須取 `paymentInfo.PayToken` 才是 PayToken 字串。
> 常見錯誤是將整個 `paymentInfo` 物件送往後端，導致後端收到 `[object Object]` 而非 Token 字串。
> 確認送往後端的值為 `typeof paymentInfo.PayToken === 'string'`。
>
> ⚠️ **PayToken 格式未定義，不可自行假設**
> ECPay 官方文件**未規範** PayToken 的字元集、編碼格式或長度上限。實際值可能包含英數字以外的字元（如 `.`、`-`、`_`、`:`、`%` 等）或為 JWT 格式（`xxx.yyy.zzz`），長度可能超過 200 字元。
> **後端不應對 PayToken 做格式驗證（如正規表達式）**，僅需確認為非空字串即可。PayToken 是 SDK 內部產生的值，不是使用者輸入。
>
> ⚠️ **errMsg 邊界行為**
> SDK 在部分錯誤狀態下可能以 `callback(null, "")` 或 `callback(undefined, undefined)` 回呼。
> 使用 `if (errMsg)` 會將空字串判為「無錯誤」而放行 `null` 值的 `paymentInfo`。
> **必須用 `if (errMsg != null)` 並額外檢查 `paymentInfo?.PayToken`**：
> ```javascript
> if (errMsg != null) { handleError(errMsg); return; }
> if (!paymentInfo?.PayToken || typeof paymentInfo.PayToken !== 'string') {
>     handleError('PayToken 無效'); return;
> }
> ```

#### 語系設定

付款介面支援切換語系，透過 `createPayment` 的第二個參數指定：

```javascript
// 語系作為 createPayment 的第二個參數傳入
ECPay.createPayment(_token, 'en-US', function(errMsg) {
    if (errMsg != null) console.error(errMsg);
}, 'V2');
```

支援語系：`zh-TW`（繁體中文，預設）、`en-US`（英文）。

查詢目前語系設定：`ECPay.getLanguage()` — 回傳當前 SDK 語系字串。

#### WebJS 範例檔案對照

| 付款方式 | WebJS 範例檔案 |
|---------|---------------|
| 信用卡 | `scripts/SDK_PHP/example/Payment/Ecpg/CreateCreditOrder/WebJS.html` |
| 分期 | `scripts/SDK_PHP/example/Payment/Ecpg/CreateInstallmentOrder/WebJS.html` |
| ATM | `scripts/SDK_PHP/example/Payment/Ecpg/CreateAtmOrder/WebJS.html` |
| 超商代碼 | `scripts/SDK_PHP/example/Payment/Ecpg/CreateCvsOrder/WebJS.html` |
| 條碼 | `scripts/SDK_PHP/example/Payment/Ecpg/CreateBarcodeOrder/WebJS.html` |
| 銀聯 | `scripts/SDK_PHP/example/Payment/Ecpg/CreateUnionPayOrder/WebJS.html` |
| Apple Pay | `scripts/SDK_PHP/example/Payment/Ecpg/CreateApplePayOrder/WebJS.html` |
| 全部 | `scripts/SDK_PHP/example/Payment/Ecpg/CreateAllOrder/WebJS.html` |
| 綁卡 | `scripts/SDK_PHP/example/Payment/Ecpg/CreateBindCardOrder/WebJS.html` |

### 步驟 2：後端建立交易

> 原始範例：`scripts/SDK_PHP/example/Payment/Ecpg/CreateOrder.php`

消費者在前端完成付款後，前端取得 `PayToken`，送到後端：

```php
$postService = $factory->create('PostWithAesJsonResponseService');
$input = [
    'MerchantID' => '3002607',
    'RqHeader'   => ['Timestamp' => time()],
    'Data'       => [
        'MerchantID'      => '3002607',
        'PayToken'        => $_POST['PayToken'],
        'MerchantTradeNo' => $_POST['MerchantTradeNo'],
    ],
];
try {
    $response = $postService->post($input, 'https://ecpg-stage.ecpay.com.tw/Merchant/CreatePayment');
    // 檢查 TransCode 是否為 1（成功）
} catch (\Exception $e) {
    error_log('站內付2.0 CreatePayment Error: ' . $e->getMessage());
}
```

> 🔍 **此步驟失敗？** ①確認 `TransCode == 1`；② 解密 `Data` 後若 `ThreeDURL` 非空，**必須導向該 URL** 完成 3D 驗證，否則交易逾時失敗；③完整排查見 [§16 3D Secure 處理](./15-troubleshooting.md#16-站內付20-3d-secure-處理遺漏)。

### 步驟 3：處理回應

> 原始範例：`scripts/SDK_PHP/example/Payment/Ecpg/GetResponse.php`

ReturnURL / OrderResultURL 收到的 POST 需要 AES 解密。

> ⚠️ **常見陷阱：OrderResultURL 是 Form POST，ReturnURL 是 JSON POST**
> 站內付 2.0 有兩個 Callback URL，格式不同（官方規格 15076.md / 9058.md）：
> - **OrderResultURL**：3D 驗證完成後，綠界透過瀏覽器 Form POST（`Content-Type: application/x-www-form-urlencoded`）將結果導至特店頁面。資料放在表單欄位 **`ResultData`**（**JSON 字串，非直接 AES 加密**），需先 `json_decode`/`JSON.parse`/`json.loads` 取外層 `{TransCode, Data}` 結構，再 AES 解密 `Data` 欄位。常見錯誤：直接對 `ResultData` AES 解密（跳過 JSON 解析步驟）。
> - **ReturnURL**：Server-to-Server POST（`Content-Type: application/json`），JSON body 直接包含三層結構（TransCode + Data），用 `json_decode(file_get_contents('php://input'))` 讀取。

兩個 URL 收到的外層 JSON 結構相同（ReturnURL 直接為 JSON body；OrderResultURL 為 `ResultData` 表單欄位內的 JSON 字串）：

```json
{
    "MerchantID": "3002607",
    "RpHeader": { "Timestamp": 1234567890 },
    "TransCode": 1,
    "TransMsg": "Success",
    "Data": "AES加密後的Base64字串"
}
```

解密處理：

```php
$aesService = $factory->create(AesService::class);

// ReturnURL 是 JSON POST（application/json），需從 php://input 讀取
$jsonBody = json_decode(file_get_contents('php://input'), true);
// OrderResultURL 則是 Form POST，$resultDataStr = $_POST['ResultData'];
// $outer = json_decode($resultDataStr, true);  ← 先 JSON 解析外層，再解密 $outer['Data']

// ⚠️ **2026-03 官方規格確認**（web_fetch 9058.md + 15076.md）：
// ReturnURL/OrderResultURL 回應結構與 CreatePayment 相同，為巢狀格式
// 先檢查 TransCode 確認 API 是否成功
$transCode = $jsonBody['TransCode'] ?? null;
if ($transCode != 1) {
    error_log('ECPay TransCode Error: ' . ($jsonBody['TransMsg'] ?? 'unknown'));
}

// 解密 Data 取得交易細節
$decryptedData = $aesService->decrypt($jsonBody['Data']);
// $decryptedData 包含：RtnCode, RtnMsg, MerchantID, OrderInfo（含 ChargeFee, ProcessFee）等

// 回應 1|OK（官方規格 9058.md）
echo '1|OK';
```

> 🔍 **此步驟失敗？** OrderResultURL 最常見錯誤：直接對 `ResultData` AES 解密（需先 `json_decode` 取出外層 `{TransCode, Data}`，再解密 `Data`）；ReturnURL 忘記回應 `1|OK`；AES 解密失敗請確認 Key/IV 取自 `HashKey`/`HashIV`（非 AIO 的 HashKey）。詳細排查見 [§17 Callback 格式混淆](./15-troubleshooting.md#17-站內付20-callback-格式混淆)。

#### Response 欄位表

所有站內付2.0 API 回應的外層結構一致：

```json
{
  "MerchantID": "3002607",
  "RpHeader": { "Timestamp": 1234567890 },
  "TransCode": 1,
  "TransMsg": "Success",
  "Data": "AES加密的Base64字串（解密後為 JSON）"
}
```

| 欄位 | 型別 | 說明 |
|------|------|------|
| MerchantID | String | 特店代號 |
| RpHeader.Timestamp | Long | 回應時間戳 |
| TransCode | Int | 外層狀態碼（1=成功） |
| TransMsg | String | 外層訊息 |
| Data | String | AES 加密的業務資料（Base64） |

Data 解密後常見欄位：

| 欄位 | 型別 | 說明 |
|------|------|------|
| RtnCode | Int | 業務結果碼（`1`=成功）**注意：是整數，不是字串**（ECPG AES-JSON 解密後為整數；對比 AIO Form POST 回呼中的字串 `"1"`）|
| RtnMsg | String | 業務結果訊息 |
| OrderInfo.MerchantTradeNo | String | 特店訂單編號（⚠️ 巢狀在 OrderInfo 內） |
| OrderInfo.TradeNo | String | ECPay 交易編號（⚠️ 巢狀在 OrderInfo 內） |
| OrderInfo.TradeAmt | Int | 交易金額 |
| OrderInfo.PaymentType | String | 付款方式（Credit/ATM/CVS/BARCODE/ApplePay/UnionPay/FlexibleInstallment） |
| OrderInfo.ChargeFee | Number | 金流服務費（2025/04/01 起為交易手續費+交易處理費總額） |
| OrderInfo.ProcessFee | Number | 交易處理費 |
| OrderInfo.TradeStatus | String | 交易狀態（`"0"`=未付款、`"1"`=已付款） |
| CardInfo | Object | 信用卡授權資訊（信用卡/銀聯卡付款時回傳） |
| ATMInfo | Object | ATM 資訊（ATM 付款時回傳） |
| CVSInfo | Object | 超商代碼資訊（CVS 付款時回傳） |
| BarcodeInfo | Object | 超商條碼資訊（Barcode 付款時回傳） |
| CustomField | String | 自訂欄位 |

#### GetToken 成功回應（Data 解密後）

GetTokenbyTrade 成功後，Data 解密得到：

```json
{
  "RtnCode": 1,
  "RtnMsg": "Success",
  "MerchantID": "3002607",
  "Token": "m12dae4846446sq",
  "TokenExpireDate": "2026/03/12 11:00:00"
}
```

> **關鍵欄位**：`Token`（String）傳給前端 JS SDK 的 `ECPay.createPayment()` 使用。
> 若 `RtnCode` 不是 1，代表 GetToken 失敗，不應繼續到前端渲染步驟。

#### CreatePayment 成功回應 — 情境 A：不需 3D 驗證（Data 解密後）

> ⚠️ **官方規格（9053.md）回應為巢狀結構**：`TradeNo` 等欄位在 `OrderInfo` 物件內，信用卡資訊在 `CardInfo` 物件內，`ThreeDURL` 在 `ThreeDInfo` 物件內。存取時請使用 `data['OrderInfo']['TradeNo']` 而非 `data['TradeNo']`。

**回應型別定義**（TypeScript 參考，適用所有語言）：

```typescript
interface CreatePaymentResponse {
  RtnCode: number;        // 1=成功
  RtnMsg: string;
  PlatformID: string;
  MerchantID: string;
  OrderInfo: {
    MerchantTradeNo: string;
    TradeNo: string;       // 綠界交易編號
    TradeAmt: number;
    TradeDate: string;     // yyyy/MM/dd HH:mm:ss
    PaymentType: string;   // "Credit" | "ATM" | ...
    PaymentDate: string;
    ChargeFee: number;
    ProcessFee: number;
    TradeStatus: string;   // "1"=已付款
  };
  CardInfo?: {             // 信用卡付款時存在
    AuthCode: string;
    Gwsr: number;
    ProcessDate: string;
    Amount: number;
    Stage: number;         // 分期期數（0=一次付清）
    Stast: number;         // 首期金額
    Staed: number;         // 各期金額
    Eci: number;
    Card6No: string;
    Card4No: string;
    IssuingBank: string;
    IssuingBankCode: string;
  };
  ThreeDInfo?: {           // 需 3D 驗證時存在
    ThreeDURL: string;     // 導向此 URL 完成驗證
  };
  ATMInfo?: {              // ATM 付款時存在
    BankCode: string;
    vAccount: string;
    ExpireDate: string;
  };
  CustomField: string;
}
```

```json
{
  "RtnCode": 1,
  "RtnMsg": "交易成功",
  "PlatformID": "",
  "MerchantID": "3002607",
  "OrderInfo": {
    "MerchantTradeNo": "test1741830960",
    "TradeNo": "2026031215360001",
    "TradeAmt": 100,
    "TradeDate": "2026/03/12 15:36:00",
    "PaymentType": "Credit",
    "PaymentDate": "2026/03/12 15:36:00",
    "ChargeFee": 3,
    "ProcessFee": 0,
    "TradeStatus": "1"
  },
  "CardInfo": {
    "AuthCode": "777777",
    "Gwsr": 10735183,
    "ProcessDate": "2026/03/12 15:36:00",
    "Amount": 100,
    "Stage": 0,
    "Stast": 0,
    "Staed": 0,
    "Eci": 5,
    "Card6No": "431195",
    "Card4No": "2222",
    "IssuingBank": "玉山銀行",
    "IssuingBankCode": "808"
  },
  "CustomField": ""
}
```

> `ThreeDInfo` 不存在或 `ThreeDInfo.ThreeDURL` 為空字串時，代表交易已完成，直接回應成功。

#### CreatePayment 成功回應 — 情境 B：需 3D 驗證（Data 解密後）

```json
{
  "RtnCode": 1,
  "RtnMsg": "Success",
  "PlatformID": "",
  "MerchantID": "3002607",
  "OrderInfo": {
    "MerchantTradeNo": "test1741830961"
  },
  "ThreeDInfo": {
    "ThreeDURL": "https://payment-stage.ecpay.com.tw/Cashier/3DVerify?tk=abc123..."
  }
}
```

> `ThreeDInfo.ThreeDURL` 非空時，代表需要 3D 驗證，**此時 `RtnCode` 可能是 1 也可能是其他值，不影響判斷**。
> 必須將用戶重導向 `ThreeDInfo.ThreeDURL`，不要誤判為失敗。

#### 3D Secure 驗證跳轉（必處理）

> ⚠️ **自 2025/8 起 3D Secure 2.0 已強制實施，幾乎所有信用卡交易的 CreatePayment 回應都會包含 `ThreeDURL`。生成代碼時此分支不可省略。**

CreatePayment 的 Data 解密後，若含 `ThreeDInfo.ThreeDURL` 欄位（非空字串），代表此筆交易需要 3D 驗證。**前端必須將消費者導向該 URL 完成驗證**，否則交易將逾時失敗。

**後端提取（必要步驟）**：CreatePayment 回應的 Data 解密後為巢狀 JSON，後端必須提取 `ThreeDInfo.ThreeDURL` 再回傳給前端。

```python
# 後端：解密 CreatePayment 回應後，提取巢狀欄位回傳給前端
data = aes_decrypt(response['Data'])  # 解密後為巢狀 JSON
three_d_url = data.get('ThreeDInfo', {}).get('ThreeDURL', '')  # ⚠️ 巢狀結構
if three_d_url:
    return jsonify({'threeDUrl': three_d_url})  # 扁平化回傳
elif data.get('RtnCode') == 1:  # ⚠️ AES-JSON 協議，RtnCode 為整數
    trade_no = data.get('OrderInfo', {}).get('TradeNo', '')  # ⚠️ 同為巢狀結構
    return jsonify({'success': True, 'tradeNo': trade_no})
else:
    return jsonify({'error': data.get('RtnMsg', '授權失敗')})
```

**前端跳轉**：

```javascript
// 前端：接收後端已扁平化的回應
const result = await response.json();

if (result.threeDUrl) {
    // 必須跳轉至 3D 驗證頁面（2025/8後幾乎必定進入此分支）
    window.location.href = result.threeDUrl;
} else if (result.success) {
    // 不需 3D 驗證，交易直接成功
    showSuccess(result);
} else {
    showError(result.error);
}
```

> **注意**：3D 驗證完成後，綠界會將結果 POST 至 OrderResultURL（前端顯示）和 ReturnURL（後端通知），流程與一般付款回呼相同。

> ⚠️ 官方文件未記載 3DS 驗證逾時時間。建議前端設定合理等待時間（如 10 分鐘），並提供使用者「重新付款」選項。若使用者放棄 3DS 驗證，該筆交易不會收到 ReturnURL Callback。

#### 框架特定實作注意事項

| 前端框架 | 正確做法 | 錯誤做法 |
|---------|---------|---------|
| **React** | `window.location.href = threeDUrl` | ❌ `navigate(threeDUrl)`（React Router 只做 SPA 內部路由，無法跳轉外部 URL） |
| **Next.js App Router** | `window.location.href = threeDUrl` | ❌ `router.push(threeDUrl)`（router.push 是 SPA 導航，不觸發完整頁面重載） |
| **Next.js Pages Router** | `window.location.href = threeDUrl` | ❌ `router.push(threeDUrl)`（同上） |
| **Vue 3** | `window.location.href = threeDUrl` | ❌ `router.push(threeDUrl)`（vue-router 不處理外部 URL） |
| **Nuxt 3** | `window.location.href = threeDUrl` | ❌ `navigateTo(threeDUrl)` 在 SSR 模式下行為不一致 |
| **純 JavaScript** | `window.location.href = threeDUrl` | ❌ `fetch(threeDUrl)`（fetch 不會改變用戶頁面） |

> **原則**：ThreeDURL 是**外部付款頁面**，必須使用瀏覽器層級的跳轉（`window.location.href`），讓整個頁面導向 ECPay 3D 驗證頁面。SPA 路由器的 `push/navigate` 只在應用內部路由，無法達到此效果。

```javascript
// ✅ 所有框架通用的正確做法
const result = await fetch('/ecpay/create-payment', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    // payToken: 來自 getPayToken callback 的 paymentInfo.PayToken（字串）
    body: JSON.stringify({ payToken, merchantTradeNo }),
}).then(r => r.json());

if (result.threeDUrl) {
    window.location.href = result.threeDUrl;  // ← 瀏覽器跳轉，所有框架都通用
} else if (result.success) {
    // 不需 3D 驗證，直接顯示成功（罕見，2025/8 後幾乎不會進入此分支）
    showSuccess();
} else {
    showError(result.error);
}
```

## 綁卡付款流程

### 步驟 1：取得綁卡 Token

> 原始範例：`scripts/SDK_PHP/example/Payment/Ecpg/GetTokenbyBindingCard.php`

```php
$input = [
    'MerchantID' => '3002607',
    'RqHeader'   => ['Timestamp' => time()],
    'Data'       => [
        'PlatformID' => '',  // 綁卡 API 可為空字串
        'MerchantID' => '3002607',
        'ConsumerInfo' => [
            'MerchantMemberID' => 'member001',
            'Email'  => 'test@example.com',
            'Phone'  => '0912345678',
            'Name'   => '測試',
            'CountryCode' => '158',
        ],
        'OrderInfo' => [
            'MerchantTradeDate' => date('Y/m/d H:i:s'),
            'MerchantTradeNo'   => 'Bind' . time(),
            'TotalAmount'       => '100',  // 綁卡驗證金額；⚠️ 綁卡 API 使用字串型別，一般付款 API 使用整數型別（Int）
            'TradeDesc'         => '綁卡驗證',
            'ItemName'          => '綁卡',
            'ReturnURL'         => 'https://你的網站/ecpay/notify',
        ],
        'OrderResultURL' => 'https://你的網站/ecpay/bind-result',
        'CustomField'    => '自訂欄位',
    ],
];
$response = $postService->post($input, 'https://ecpg-stage.ecpay.com.tw/Merchant/GetTokenbyBindingCard');
```

### 步驟 2：前端 3D 驗證後建立綁卡

> 原始範例：`scripts/SDK_PHP/example/Payment/Ecpg/CreateBindCard.php`

```php
$input = [
    'MerchantID' => '3002607',
    'RqHeader'   => ['Timestamp' => time()],
    'Data'       => [
        'MerchantID'       => '3002607',
        'BindCardPayToken' => $_POST['BindCardPayToken'],
        'MerchantMemberID' => 'member001',
    ],
];
$response = $postService->post($input, 'https://ecpg-stage.ecpay.com.tw/Merchant/CreateBindCard');
```

### 步驟 3：處理綁卡結果

> 原始範例：`scripts/SDK_PHP/example/Payment/Ecpg/GetCreateBindCardResponse.php`

```php
$resultData = json_decode($_POST['ResultData'], true);
$aesService = $factory->create(AesService::class);
$decrypted = $aesService->decrypt($resultData['Data']);
// $decrypted 包含：BindCardID, CardInfo (Card6No, Card4No 等), OrderInfo
```

### 步驟 4：日後用綁卡扣款

> 原始範例：`scripts/SDK_PHP/example/Payment/Ecpg/CreatePaymentWithCardID.php`

```php
$input = [
    'MerchantID' => '3002607',
    'RqHeader'   => ['Timestamp' => time()],
    'Data'       => [
        'PlatformID' => '',
        'MerchantID' => '3002607',
        'BindCardID' => '綁卡時取得的ID',
        'OrderInfo'  => [
            'MerchantTradeDate' => date('Y/m/d H:i:s'),
            'MerchantTradeNo'   => 'Pay' . time(),
            'TotalAmount'       => 500,
            'ReturnURL'         => 'https://你的網站/ecpay/notify',
            'TradeDesc'         => '綁卡扣款',
            'ItemName'          => '商品',
        ],
        'ConsumerInfo' => [
            'MerchantMemberID' => 'member001',
            'Email'  => 'test@example.com',
            'Phone'  => '0912345678',
            'Name'   => '測試',
            'CountryCode' => '158',
            'Address'=> '測試地址',
        ],
        'CustomField' => '',
    ],
];
$response = $postService->post($input, 'https://ecpg-stage.ecpay.com.tw/Merchant/CreatePaymentWithCardID');
```

## 會員綁卡管理

### 查詢會員綁卡

> 原始範例：`scripts/SDK_PHP/example/Payment/Ecpg/GetMemberBindCard.php`

```php
$input = [
    'MerchantID' => '3002607',
    'RqHeader'   => ['Timestamp' => time()],
    'Data'       => [
        'PlatformID'       => '',
        'MerchantID'       => '3002607',
        'MerchantMemberID' => 'member001',
        'MerchantTradeNo'  => 'Query' . time(),
    ],
];
$response = $postService->post($input, 'https://ecpg-stage.ecpay.com.tw/Merchant/GetMemberBindCard');
```

### 刪除會員綁卡

> 原始範例：`scripts/SDK_PHP/example/Payment/Ecpg/DeleteMemberBindCard.php`

```php
$input = [
    'MerchantID' => '3002607',
    'RqHeader'   => ['Timestamp' => time()],
    'Data'       => [
        'PlatformID' => '',
        'MerchantID' => '3002607',
        'BindCardID' => '要刪除的綁卡ID',
    ],
];
$response = $postService->post($input, 'https://ecpg-stage.ecpay.com.tw/Merchant/DeleteMemberBindCard');
```

### 綁卡管理（讓消費者自行管理綁定的信用卡）

> 原始範例：`scripts/SDK_PHP/example/Payment/Ecpg/DeleteCredit.php`

此端點 `GetTokenbyUser` 取得 Token 後，消費者可在綠界管理頁面中自行檢視和刪除已綁定的信用卡。

```php
$input = [
    'MerchantID' => '3002607',
    'RqHeader'   => ['Timestamp' => time()],
    'Data'       => [
        'MerchantID'  => '3002607',
        'ConsumerInfo'=> [
            'MerchantMemberID' => 'member001',
            'Email'  => 'test@example.com',
            'Phone'  => '0912345678',
            'Name'   => '測試',
            'CountryCode' => '158',
        ],
    ],
];
$response = $postService->post($input, 'https://ecpg-stage.ecpay.com.tw/Merchant/GetTokenbyUser');
```

## 請款 / 退款

> 原始範例：`scripts/SDK_PHP/example/Payment/Ecpg/Capture.php`

> ⚠️ **測試環境限制**：官方規格（9073.md）明確指出「測試環境：因無法提供實際授權，故無法使用此 API」。DoAction 僅能在**正式環境**使用，測試環境無法執行請款/退款/取消/放棄操作。

**注意**：站內付2.0 的請款/退款端點在 `ecpayment.ecpay.com.tw`（正式），不是 `ecpg`。

```php
$input = [
    'MerchantID' => '3002607',
    'RqHeader'   => ['Timestamp' => time()],
    'Data'       => [
        'PlatformID'      => '',         // 一般商店填空字串；平台商模式填平台商 ID
        'MerchantID'      => '3002607',
        'MerchantTradeNo' => '你的訂單編號',
        'TradeNo'         => '綠界交易編號',
        'Action'          => 'C',  // C=請款, R=退款, E=取消, N=放棄
        'TotalAmount'     => 100,
        'CustomField'     => '',   // 選填：自訂欄位 String(40)，僅支援英數字 a-zA-z0-9 與 – _ / . :
    ],
];
// ⚠️ DoAction 僅正式環境可用（測試環境無此 API）
// 正式環境：https://ecpayment.ecpay.com.tw/1.0.0/Credit/DoAction
$response = $postService->post($input, 'https://ecpayment.ecpay.com.tw/1.0.0/Credit/DoAction');
```

## 定期定額管理

> 原始範例：`scripts/SDK_PHP/example/Payment/Ecpg/CreditPeriodAction.php`

> **定期定額付款結果通知**：當 `PaymentUIType=0` 時，需填入 `PeriodReturnURL`，每次定期定額授權執行後，ECPay 會將結果 POST 至此 URL。格式與一般付款結果通知相同（AES 加密 JSON），但觸發時機為每期自動扣款完成後。
> 官方規格：`references/Payment/站內付2.0API技術文件Web.md` — 付款 / 付款結果通知 / 定期定額

```php
$input = [
    'MerchantID' => '3002607',
    'RqHeader'   => ['Timestamp' => time()],
    'Data'       => [
        'PlatformID'      => '',         // 一般商店填空字串；平台商模式填平台商 ID
        'MerchantID'      => '3002607',
        'MerchantTradeNo' => '你的訂閱訂單編號',
        'Action'          => 'ReAuth',  // ReAuth=重新授權, Cancel=取消
    ],
];
$response = $postService->post($input, 'https://ecpayment-stage.ecpay.com.tw/1.0.0/Cashier/CreditCardPeriodAction');
```

## 查詢

### 一般查詢

> 原始範例：`scripts/SDK_PHP/example/Payment/Ecpg/QueryTrade.php`

```php
$input = [
    'MerchantID' => '3002607',
    'RqHeader'   => ['Timestamp' => time()],
    'Data'       => [
        'PlatformID'      => '',         // 一般商店填空字串；平台商模式填平台商 ID
        'MerchantID'      => '3002607',
        'MerchantTradeNo' => '你的訂單編號',
    ],
];
$response = $postService->post($input, 'https://ecpayment-stage.ecpay.com.tw/1.0.0/Cashier/QueryTrade');
```

### 信用卡交易查詢

> 原始範例：`scripts/SDK_PHP/example/Payment/Ecpg/QueryCreditTrade.php`

端點：`POST https://ecpayment-stage.ecpay.com.tw/1.0.0/CreditDetail/QueryTrade`

### 付款資訊查詢

> 原始範例：`scripts/SDK_PHP/example/Payment/Ecpg/QueryPaymentInfo.php`

端點：`POST https://ecpayment-stage.ecpay.com.tw/1.0.0/Cashier/QueryPaymentInfo`

### 定期定額查詢

> 原始範例：`scripts/SDK_PHP/example/Payment/Ecpg/QueryPeridicTrade.php`

端點：`POST https://ecpayment-stage.ecpay.com.tw/1.0.0/Cashier/QueryTrade`（同一般查詢端點）

## 對帳

> 原始範例：`scripts/SDK_PHP/example/Payment/Ecpg/QueryTradeMedia.php`

此 API 需要手動 AES 加解密，且回傳 CSV 而非 JSON，因此使用 `CurlService` 手動設定 header：

```php
use Ecpay\Sdk\Services\AesService;
use Ecpay\Sdk\Services\CurlService;

$aesService = $factory->create(AesService::class);
$curlService = $factory->create(CurlService::class);

$data = [
    'MerchantID'  => '3002607',
    'DateType'    => '2',
    'BeginDate'   => '2025-01-01',
    'EndDate'     => '2025-01-31',
    'PaymentType' => '01',  // 注意：是 '01' 不是 '0'
];

$input = [
    'MerchantID' => '3002607',
    'RqHeader'   => ['Timestamp' => time()],
    'Data'       => $aesService->encrypt($data),
];

// 手動設定 JSON header 並呼叫
$curlService->setHeaders(['Content-Type:application/json']);
$result = $curlService->run(json_encode($input), 'https://ecpayment-stage.ecpay.com.tw/1.0.0/Cashier/QueryTradeMedia');

// 回傳是 CSV 檔案內容，直接存檔
$filepath = 'QueryTradeMedia' . time() . '.csv';
file_put_contents($filepath, $result);
```

> **注意**：此 API 回傳的是 CSV 格式的對帳資料，不是 JSON。需用 `CurlService` 的 `run()` 方法（而非 `post()`）並手動設定 `Content-Type:application/json` header。


> 📖 **Web vs App 整合差異**（iOS/Android/React Native 原生 SDK + WebView）已移至 [02c-ecpg-app-production.md](./02c-ecpg-app-production.md)

## 安全注意事項

> ⚠️ **安全必做清單**
> 1. 驗證 MerchantID 為自己的
> 2. 比對金額與訂單記錄
> 3. 防重複處理（記錄已處理的 MerchantTradeNo）
> 4. 異常時仍回應 `1|OK`（避免重送風暴）
> 5. 記錄完整日誌（遮蔽 HashKey/HashIV）

### GetResponse 安全處理

AES 解密後務必驗證：

```php
// ⚠️ AES-JSON 協定：加密資料在 JSON body 中，非 Form POST
$input = json_decode(file_get_contents('php://input'), true);
$decryptedData = $aesService->decrypt($input['Data']);

// 驗證 MerchantID
if ($decryptedData['MerchantID'] !== env('ECPAY_MERCHANT_ID')) {
    error_log('站內付2.0: MerchantID mismatch');
    return;
}

// 驗證金額一致性
$order = findOrder($decryptedData['OrderInfo']['MerchantTradeNo']);
if ((int)$decryptedData['OrderInfo']['TradeAmt'] !== $order->amount) {
    error_log('站內付2.0: Amount mismatch');
    return;
}

// 冪等性檢查
if ($order->isPaid()) {
    return;
}
```

### Content Security Policy (CSP)

若你的網站設有嚴格 CSP，需允許站內付2.0 JavaScript SDK 的 domain：

```
Content-Security-Policy: script-src 'self' https://ecpg-stage.ecpay.com.tw https://ecpg.ecpay.com.tw;
                         frame-src 'self' https://ecpg-stage.ecpay.com.tw https://ecpg.ecpay.com.tw;
                         connect-src 'self' https://ecpg-stage.ecpay.com.tw https://ecpg.ecpay.com.tw;
```

> 正式環境只需保留 `https://ecpg.ecpay.com.tw`，移除 `-stage`。

### CORS 注意事項

站內付2.0 API 為 server-to-server 呼叫，**不可從前端直接呼叫**（會被 CORS 擋住）。正確架構：

1. 前端：使用站內付2.0 JavaScript SDK 取得 Token
2. 後端：用 Token 呼叫 CreatePayment API
3. 前端**不要**直接呼叫 `ecpg.ecpay.com.tw` 的 API

### Token 安全存儲

若使用綁卡功能，Token 應妥善保管：

- Token 存儲在資料庫中應加密（AES-256 或使用 KMS）
- 不要將 Token 傳到前端或寫入日誌
- 設定 Token 過期機制（定期清理不活躍的綁卡）
- ECPay 的 Token 不等同信用卡卡號，但仍屬敏感資訊

### 防止重複付款

消費者可能重複點擊付款按鈕。建議：

1. **前端**：點擊後立即 disable 按鈕
2. **後端**：同一 `MerchantTradeNo` 不重複建立交易
3. **資料庫**：對 `MerchantTradeNo` 建立 UNIQUE constraint

## AI 生成代碼常見錯誤

> **本節為 AI Agent 生成站內付 2.0 非 PHP 代碼時最常犯的錯誤。** 生成代碼時請逐項比對確認。

| # | 錯誤行為 | 正確做法 | 後果 |
|---|---------|---------|------|
| 1 | 把 `QueryTrade` / `DoAction` 打到 `ecpg` domain | 查詢/退款端點在 **`ecpayment`** domain（見頂部 Domain 路由表） | HTTP 404 |
| 2 | `Timestamp` 用毫秒（`Date.now()`） | 必須用 **Unix 秒**（`Math.floor(Date.now()/1000)` 或 `int(time.time())`） | TransCode ≠ 1，TransMsg: Timestamp invalid |
| 3 | `MerchantID` 只放在外層，Data 內省略 | **外層和 Data 內層各一份**（參考 CreateCreditOrder/GetToken.php 第 17 行） | TransCode ≠ 1 |
| 4 | `RqHeader` 加了 `Revision` 欄位 | 站內付 2.0 的 RqHeader **只有 `Timestamp`**（發票/物流才有 Revision） | TransCode ≠ 1 |
| 5 | Data 加密用 `ecpayUrlEncode`（有 lowercase + .NET 替換） | 站內付 Data 加密用 **`aesUrlEncode`**（只做 `urlencode`，不做 lowercase 和 `.NET 替換`） | ECPay 端解密失敗，TransCode ≠ 1 |
| 6 | CreatePayment 回應未處理 `ThreeDURL` | Data 解密後必須判斷 `ThreeDURL` 是否非空，非空則**導向該 URL**（2025/8 後幾乎必定有） | 交易逾時失敗，消費者無法完成付款 |
| 7 | `OrderResultURL` 收到後用 `request.json()` 解析 | OrderResultURL 是**瀏覽器 Form POST**（`application/x-www-form-urlencoded`），資料在表單欄位 `ResultData`，不在 JSON body | 解析失敗，取不到資料 |
| 8 | ReturnURL callback 回應 JSON（如 `{"status":"ok"}`） | ReturnURL 必須回應純文字 **`1\|OK`**（無引號、無換行）。HTTP Status 必須是 200 | 綠界持續重試（最多 4 次/天） |
| 9 | 把 AIO 的 `=== '1'`（字串）用在 ECPG，或把 ECPG 的 `=== 1`（整數）用在 AIO | **ECPG** Data 是 AES-JSON 解密後的 PHP 陣列，`RtnCode` 是 **整數**，正確比較為 `=== 1`。**AIO** ReturnURL 是 Form POST（`$_POST`），`RtnCode` 是 **字串** `"1"`，正確比較為 `=== '1'`。兩者不可互換 | 付款已成功但誤判為失敗 |
| 10 | 只檢查 `RtnCode` 不先檢查 `TransCode` | 必須先確認 `TransCode == 1`（傳輸層成功），才解密 Data 並檢查 `RtnCode`（業務層成功） | TransCode 失敗時 Data 可能是錯誤訊息而非加密資料，強行解密導致例外 |
| 11 | ATM/CVS 取號後等待 ReturnURL 立即到來 | ATM/CVS CreatePayment 成功後應**解析 Data 顯示付款指示**（虛擬帳號/超商代碼），ReturnURL 是**消費者實際繳款後**才非同步送達（見本文件「非信用卡付款」節） | 誤判流程卡住或付款失敗，消費者沒拿到繳費資訊 |
| 12 | Apple Pay 按鈕在前端設定後仍不出現 | Apple Pay 必須先完成**三個前置步驟**：① 在 Apple Developer 建立 Merchant ID、② 將域名驗證檔放到 `/.well-known/apple-developer-merchantid-domain-association`、③ 上傳憑證到綠界後台（見本文件「Apple Pay 整合前置準備」節） | Apple Pay 按鈕永遠不顯示，無錯誤訊息 |
| 13 | `getPayToken` callback 第一個參數當作字串直接用（`function(payToken, errMsg)`） | 第一個參數是**物件**，必須取 `paymentInfo.PayToken`（見官方 SDK 範例 `WebJS.html`） | 後端收到 `[object Object]`，CreatePayment 失敗 |
| 14 | 用 `if (errMsg)` 檢查 SDK callback 錯誤 | 必須用 **`if (errMsg != null)`**（官方 SDK 寫法）。`errMsg` 空字串是 falsy 但不代表無錯誤，此時 `paymentInfo` 可能為 null | `null.PayToken` 拋例外；或 null 被送到後端 |
| 15 | 對 PayToken 做正規表達式格式驗證（如 `/^[a-f0-9]+$/`、限制長度 ≤ 200） | PayToken 是 SDK 內部產生的值，**格式未定義**（可能含 `.`、`-`、`:`、`%` 等字元，或為 JWT 格式，長度可能超過 200）。僅需確認 `typeof === 'string' && length > 0` | 合法 PayToken 被後端攔截，付款失敗 |

> 完整錯誤碼含義見 [guides/15-troubleshooting.md](./15-troubleshooting.md) 和 [guides/21-error-codes-reference.md](./21-error-codes-reference.md)

## 完整範例檔案對照

| 檔案 | 用途 | 端點 |
|------|------|------|
| CreateAllOrder/GetToken.php | 全方位 Token | ecpg/GetTokenbyTrade |
| CreateCreditOrder/GetToken.php | 信用卡 Token | ecpg/GetTokenbyTrade |
| CreateInstallmentOrder/GetToken.php | 分期 Token | ecpg/GetTokenbyTrade |
| CreateAtmOrder/GetToken.php | ATM Token | ecpg/GetTokenbyTrade |
| CreateCvsOrder/GetToken.php | CVS Token | ecpg/GetTokenbyTrade |
| CreateBarcodeOrder/GetToken.php | 條碼 Token | ecpg/GetTokenbyTrade |
| CreateUnionPayOrder/GetToken.php | 銀聯 Token | ecpg/GetTokenbyTrade |
| CreateApplePayOrder/GetToken.php | Apple Pay Token | ecpg/GetTokenbyTrade |
| CreateOrder.php | 建立交易 | ecpg/CreatePayment |
| GetResponse.php | 回應解密 | — |
| GetTokenbyBindingCard.php | 綁卡 Token | ecpg/GetTokenbyBindingCard |
| CreateBindCard.php | 建立綁卡 | ecpg/CreateBindCard |
| GetCreateBindCardResponse.php | 綁卡結果 | — |
| CreatePaymentWithCardID.php | 綁卡扣款 | ecpg/CreatePaymentWithCardID |
| GetMemberBindCard.php | 查詢綁卡 | ecpg/GetMemberBindCard |
| DeleteMemberBindCard.php | 刪除綁卡 | ecpg/DeleteMemberBindCard |
| DeleteCredit.php | 刪除信用卡 | ecpg/GetTokenbyUser |
| Capture.php | 請款/退款 | ecpayment/Credit/DoAction |
| CreditPeriodAction.php | 定期定額管理 | ecpayment/CreditCardPeriodAction |
| QueryTrade.php | 查詢訂單 | ecpayment/QueryTrade |
| QueryCreditTrade.php | 信用卡查詢 | ecpayment/CreditDetail/QueryTrade |
| QueryPaymentInfo.php | 付款資訊查詢 | ecpayment/QueryPaymentInfo |
| QueryPeridicTrade.php | 定期定額查詢 | ecpayment/QueryTrade |
| QueryTradeMedia.php | 對帳 | ecpayment/QueryTradeMedia |


> 📖 **Apple Pay 前置準備 + 正式環境注意事項 + 切換清單**已移至 [02c-ecpg-app-production.md](./02c-ecpg-app-production.md#apple-pay-整合前置準備)
