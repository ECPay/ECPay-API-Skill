> 對應 ECPay API 版本 | 基於 PHP SDK ecpay/sdk | 最後更新：2026-03

# 站內付 2.0 完整指南

> ⚠️ **站內付2.0 使用兩個不同 Domain — 打錯立得 HTTP 404**
>
> | API 類別 | 測試 Domain | 正式 Domain |
> |---------|------------|------------|
> | Token 取得 API（**注意**：CreatePayment 同屬此 domain） | `ecpg-stage.ecpay.com.tw` | `ecpg.ecpay.com.tw` |
> | 查詢 / 請款 / 退款 API | `ecpayment-stage.ecpay.com.tw` | `ecpayment.ecpay.com.tw` |
>
> 先確認 Domain 再開始撰寫程式碼。

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
> | RqHeader 欄位 | 站內付 2.0 的 RqHeader **只有 `Timestamp`**，無 `Revision` 欄位（發票/物流才有） |
> | Data 欄位加密 | PHP SDK 的 `PostWithAesJsonResponseService` 會自動加密 Data。非 PHP 必須手動做：`json_encode → urlencode → AES-128-CBC 加密 → base64_encode`（詳見 [guides/14](./14-aes-encryption.md)） |
> | AES URL Encode | 站內付使用 **`aesUrlEncode`**（只做 `urlencode`，不做 lowercase 和 .NET 替換），絕對不可使用 CheckMacValue 的 `ecpayUrlEncode` |
>
> **③ ThreeDURL — 2025/8 起幾乎所有信用卡交易都需要處理**
>
> CreatePayment 回應的 Data 解密後，若含 `ThreeDURL` 欄位（非空字串），**必須將用戶導向該 URL** 完成 3D 驗證。忘記此分支會導致交易逾時失敗。生成代碼時此分支不可省略。
>
> **④ Callback 回應格式**
>
> - **ReturnURL**（Server-to-Server, Content-Type: application/json）：讀取 JSON body → 回應純文字 `1|OK`
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
| [**🚀 首次串接快速路徑**](#-首次串接最快成功路徑) | **⭐ 新手從這裡開始 — 5 步驟分段驗證流程** |
| [**⚡ 完整可執行範例（信用卡 / ATM / CVS）**](#-完整可執行範例pythonnodejs) | **複製即可跑 — 信用卡（Python Flask + Node.js Express）及 ATM/CVS（Python Flask）** |
| [**⚠️ ATM / CVS 首次串接快速路徑**](#atm--cvs-首次串接快速路徑) | **ATM/CVS 開發者從這裡看 GetToken 參數差異** |
| [**⚠️ 非信用卡（ATM/CVS/Barcode）Callback 時序**](#非信用卡付款atm--cvs--barcode-的-callback-時序) | **ATM/CVS ReturnURL 非同步說明 — 信用卡以外必讀** |
| [**🖥️ SPA / React / Vue / Next.js 整合架構**](#-spa--react--vue--nextjs-整合架構) | **前後端分離架構陷阱 — ThreeDURL 不可用 router.push** |
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
| [Web vs App 整合差異](#web-vs-app-整合差異) | iOS/Android 原生 SDK + WebView |
| [安全注意事項](#安全注意事項) | GetResponse 安全處理 |
| [**Apple Pay 整合前置準備**](#apple-pay-整合前置準備) | **域名驗證、Merchant ID、憑證設定 — Apple Pay 必讀** |
| [正式環境實作注意事項](#正式環境實作注意事項) | Token 刷新策略、ReturnURL 冪等性、錯誤降級 |
| [正式環境切換清單](#正式環境切換清單) | 測試 → 正式環境的完整 Checklist |
| [**AI 生成代碼常見錯誤**](#ai-生成代碼常見錯誤) | **⚠️ 生成非 PHP 代碼前必讀 — 12 個高頻錯誤** |

## 🚀 首次串接：最快成功路徑

> **目標**：完成從 GetToken 到第一筆成功交易的完整流程。按步驟逐一驗證，每步確認成功後再繼續。
>
> 已熟悉 AES-JSON 協議並備妥環境？可直接跳到 [一般付款流程](#一般付款流程)。

### 本地開發環境快速設定（可選）

> 沒有公開可訪問的 URL？**3 步驟建立 ngrok 隧道**，讓本機端點接收 ECPay callback：

```bash
# 1. 安裝 ngrok（擇一）
brew install ngrok          # macOS
choco install ngrok         # Windows（需先安裝 Chocolatey）
# 或直接下載：https://ngrok.com/download

# 2. 啟動隧道（以本機 3000 port 為例）
ngrok http 3000

# 3. 複製 Forwarding URL（格式如 https://a1b2c3d4.ngrok-free.app）
#    → 設為 ReturnURL 和 OrderResultURL 的前綴
```

> **零安裝替代方案**：用 [RequestBin](https://requestbin.com/r) 建立臨時端點接收 callback，查看完整 JSON 結構後再實作解析邏輯。
> **ngrok 注意事項**：每次重啟 ngrok 後 URL 會改變，需重新更新 ReturnURL / OrderResultURL 並重新呼叫 GetTokenbyTrade。

### 串接前確認清單

| 項目 | 測試環境值 | 確認 |
|------|-----------|:----:|
| MerchantID | `3002607` | □ |
| HashKey | `pwFHCqoQZGmho4w6`（16 bytes） | □ |
| HashIV | `EkRm7iFT261dpevs`（16 bytes） | □ |
| **AES-128-CBC 加解密已實作**（站內付 2.0 核心依賴）| 見 [guides/14](./14-aes-encryption.md) | □ |
| 後端 ReturnURL 端點可接收 HTTP POST | — | □ |
| 後端 OrderResultURL 端點可接收 HTTP POST | — | □ |
| 前端頁面可引入外部 JavaScript | — | □ |

> ⚠️ **非 PHP 語言**：AES-128-CBC 加解密是站內付 2.0 所有請求的基礎。**開始撰寫任何業務代碼前，請先讀 [guides/14-aes-encryption.md](./14-aes-encryption.md) 並確認你的語言的 AES 實作正確**（含 URL Encode 前置步驟）。省略這步是導致非 PHP 串接失敗的首要原因。

> **ReturnURL / OrderResultURL 尚未準備好？** 先用 [RequestBin](https://requestbin.com) 或 `ngrok http 3000` 建立暫時端點，確認付款流程後再串接正式 callback 邏輯。無公開 URL 的替代方案見本節末尾。

### GetTokenbyTrade Data 必填欄位速查

> 📋 根據官方規格（`references/Payment/站內付2.0API技術文件Web.md` §取得廠商驗證碼/付款）整理。
> 程式碼生成前請對照此表確認每個欄位是否應填入。

| 欄位路徑 | 類型 | 必填？ | 說明 / 常見陷阱 |
|---------|------|:------:|----------------|
| `MerchantID`（Data 內） | String(10) | ✅ 必填 | 外層也有一個，**兩處都要填**（最常漏的坑） |
| `RememberCard` | Int | ✅ 必填 | `0`=不綁卡（測試時用這個）`1`=啟用綁卡（= 1 時 `ConsumerInfo.MerchantMemberID` 也必填） |
| `PaymentUIType` | Int | ✅ 必填 | `0`=定期定額 `1`=信用卡一次付清（`OrderResultURL` 必填）`2`=付款選擇清單頁（一般使用）`5`=Apple Pay 延遲付款 |
| `ChoosePaymentList` | String(30) | ✅ PaymentUIType=2 時必填 | **字串型別，不是整數**：`'0'`=全部 `'1'`=信用卡 `'2'`=分期 `'3'`=ATM `'4'`=CVS `'5'`=超商條碼 `'6'`=銀聯卡 `'7'`=Apple Pay `'8'`=永豐30期；多選：`'1,3,4'` |
| `OrderInfo.MerchantTradeDate` | String(20) | ✅ 必填 | 格式：`'yyyy/MM/dd HH:mm:ss'`（GMT+8，非 Unix timestamp） |
| `OrderInfo.MerchantTradeNo` | String(20) | ✅ 必填 | 每次唯一；英數字 a-zA-Z0-9，最長 20 字元；**GetToken 與 CreatePayment 必須完全相同** |
| `OrderInfo.TotalAmount` | Int | ✅ 必填 | 整數（不含小數點），新台幣 |
| `OrderInfo.ReturnURL` | String(200) | ✅ 必填 | 公開 HTTPS URL（localhost/127.0.0.1 無效） |
| `OrderInfo.TradeDesc` | String(200) | ✅ 必填 | 交易描述 |
| `OrderInfo.ItemName` | String(400) | ✅ 必填 | 商品名稱（多件用 `#` 分隔） |
| `CardInfo.OrderResultURL` | String(200) | ✅ PaymentUIType=0,1 或 ChoosePaymentList 含 0,1,2 時必填 | **ATM / CVS / Barcode 不需要**；**⚠️ 銀聯卡(6)需用 `UnionPayInfo.OrderResultURL`，不是此欄位** |
| `CardInfo.Redeem` | Int | — 選填 | `0`=不使用紅利（預設）`1`=使用紅利 |
| `ATMInfo.ExpireDate` | Int | ✅ ChoosePaymentList=3 時必填 | 繳費有效天數（1~60，預設 3） |
| `CVSInfo.StoreExpireDate` | Int | ✅ ChoosePaymentList=4 時必填 | 分鐘數（預設 10080=7天，最長 43200=30天） |
| `BarcodeInfo.StoreExpireDate` | Int | ✅ ChoosePaymentList=5 時必填 | 天數（預設 7，最長 30） |
| `ConsumerInfo`（整體 Object） | Object | ✅ **必填** | **整個 Object 不可省略**，即使只填最少欄位也必須傳入 |
| `ConsumerInfo.Email` | String(100) | ✅ Email / Phone **擇一**必填 | 格式需符合 email 正規表達式；可與 Phone 同時填 |
| `ConsumerInfo.Phone` | String(60) | ✅ Email / Phone **擇一**必填 | 台灣格式如 `'0912345678'`；海外需加國碼如 `'886912345678'` |
| `ConsumerInfo.MerchantMemberID` | String(60) | ✅ RememberCard=1 時必填 | 你系統的會員 ID；RememberCard=0 時選填 |
| `ConsumerInfo.Name` | String(50) | — 選填 | 消費者姓名 |
| `ConsumerInfo.CountryCode` | String(3) | — 選填 | ISO3166 國別碼（台灣：`'158'`） |
| `PlatformID` | String(10) | — 選填 | 平台商才需要；一般特店留空或省略 |

> ⚠️ **ConsumerInfo 是整體必填（官方規格明確標示 必填）**：若完全省略 `ConsumerInfo` 物件，API 會回傳 `TransCode=0`（加密驗證失敗）或業務層錯誤。最少需傳入 `Email` 或 `Phone` 其中一個。

> ⏱️ **流程約束與 Token 生命週期（必讀，避免反覆試誤）**
>
> | 項目 | 有效期 / 規則 |
> |------|------------|
> | Token（GetTokenbyTrade 回傳）| **10 分鐘** — 超時後步驟 2 的付款 UI 失效，需重新呼叫 GetTokenbyTrade |
> | MerchantTradeNo | 步驟 1（GetToken）與步驟 4（CreatePayment）**必須使用完全相同的值** |
> | 重新開始流程 | 必須產生**新的** MerchantTradeNo，再重新呼叫步驟 1——舊 Token 和舊 MerchantTradeNo 均不可重用 |
> | PayToken | 步驟 3 取得後，由步驟 4（CreatePayment）**一次性消耗**，不可重複使用 |
>
> **常見反覆試誤原因**：調試超過 10 分鐘 → Token 過期 → 步驟 2 表單消失 → 未更新 MerchantTradeNo 直接重送步驟 1 → `RtnCode ≠ 1`（MerchantTradeNo 重複）。**解法**：每次重新開始時，先產生包含時間戳的新 MerchantTradeNo（例如 `'Test' + str(int(time.time()))`）。

#### ⏱️ Token 生命週期時序圖

```
後端                          前端瀏覽器                    消費者
 │                               │                             │
t=0  ── GetTokenbyTrade ────────►│  Token（有效 10 分鐘）      │
 │      同時確定 MerchantTradeNo  │                             │
 │                               │                             │
t+0  ◄── Token ─────────────────│  JS SDK createPayment(token)│
 │                               │──────────────────────────► │ 看到信用卡表單
 │                               │                             │ 填入卡號...
 │                               │                             │
t+2  ◄── getPayToken 回呼 ───────│◄─────────── PayToken ───────│ （一次性）
 │                               │                             │
t+2  ── CreatePayment(PayToken)─►│                             │
 │      同一 MerchantTradeNo      │                             │
 │                               │                             │
t+3  ◄── ThreeDURL（或 RtnCode=1）│                             │
 │    ──── window.location.href ─►│──────────────────────────► │ 3D 驗證頁面
 │                               │                             │ 驗證完成...
 │                               │                             │
t+4  ◄── ReturnURL（S2S JSON）── ECPay Server                  │
 │       回應 '1|OK'              │                             │
 │                               │◄─── OrderResultURL（Form）  │
 │                               │     渲染結果頁給消費者       │
 │
t=10 ⚠️ Token 過期（若仍在調試）
     ↓
     🔄 重置步驟：
     1. 產生新 MerchantTradeNo（例如 'Test' + str(int(time.time()))）
     2. 重新呼叫 GetTokenbyTrade（步驟 1）
     3. 舊 Token / 舊 MerchantTradeNo 完全作廢，勿重用
```

### 5 步驟分段驗證流程

```
【步驟 0】驗證 AES 加密環境（非 PHP 必做，PHP 可略過）
  步驟 1 ▶ 後端呼叫 GetTokenbyTrade   → 取得 Token（字串）
  步驟 2 ▶ 前端 JS SDK createPayment  → 顯示付款表單（看到信用卡號欄位）
  步驟 3 ▶ 消費者填卡 → getPayToken   → 取得 PayToken（字串）
  步驟 4 ▶ 後端呼叫 CreatePayment     → 取得 ThreeDURL 或 RtnCode=1
  步驟 5 ▶ 前端導向 ThreeDURL + 接收 Callback（ReturnURL JSON + OrderResultURL Form）
```

---

#### 步驟 0：環境預檢（非 PHP 必做，PHP 可略過）

**目標**：在開始 5 步驟流程之前，用測試帳號發送最小化 GetTokenbyTrade 請求，確認 `TransCode: 1`——代表 AES 加密環境設定正確。

**為何要做**：非 PHP 語言須手動實作 AES-128-CBC（json → urlEncode → AES → base64），任一步錯誤都會讓所有步驟的 `TransCode ≠ 1`。先用最小請求隔離「加密問題」，再串接完整流程，可節省數小時除錯時間。

> **ConsumerInfo 必填提醒**：整個 `ConsumerInfo` 物件為**必填**，不可省略。最少需傳入 `Email` 或 `Phone` 其中一個。下方範例使用 `RememberCard: 1`（啟用綁卡），此時 `MerchantMemberID` 也是必填；若只是測試，可改 `RememberCard: 0` 並省略 `MerchantMemberID`。

**Python（pip install pycryptodome requests）**：

```python
import json, time, base64, urllib.parse, requests
from Crypto.Cipher import AES
from Crypto.Util.Padding import pad

KEY = b'pwFHCqoQZGmho4w6'   # 16 bytes
IV  = b'EkRm7iFT261dpevs'   # 16 bytes

def aes_encrypt(data: dict) -> str:
    """aesUrlEncode（只 urlencode，不 lowercase）+ AES-128-CBC + base64"""
    s = json.dumps(data, ensure_ascii=False, separators=(',', ':'))
    u = urllib.parse.quote_plus(s).replace('~', '%7E')
    c = AES.new(KEY, AES.MODE_CBC, IV)
    return base64.b64encode(c.encrypt(pad(u.encode('utf-8'), 16))).decode()

resp = requests.post(
    'https://ecpg-stage.ecpay.com.tw/Merchant/GetTokenbyTrade',
    json={
        "MerchantID": "3002607",
        "RqHeader": {"Timestamp": int(time.time())},       # Unix 秒
        "Data": aes_encrypt({
            "MerchantID": "3002607",                       # Data 內也需要
            "RememberCard": 1, "PaymentUIType": 2, "ChoosePaymentList": "1",
            "OrderInfo": {
                "MerchantTradeDate": "2026/03/12 10:00:00",
                "MerchantTradeNo": f"precheck{int(time.time())}",
                "TotalAmount": 100,
                "ReturnURL": "https://example.com/notify",
                "TradeDesc": "預檢", "ItemName": "預檢"
            },
            "CardInfo": {"Redeem": 0, "OrderResultURL": "https://example.com/result"},
            "ConsumerInfo": {
                "MerchantMemberID": "m1",        # ← RememberCard=1 時必填；=0 時可省略
                "Email": "t@t.com",              # ← Email 或 Phone 擇一必填
                "Phone": "0912345678",           # ← 選填（但 Email+Phone 都填更完整）
                "Name": "測試",                  # ← 選填
                "CountryCode": "158"             # ← 選填（台灣）
            }
        })
    }
)
print(resp.json())
# ✅ 成功：{"TransCode": 1, "TransMsg": "Success", "Data": "<Base64字串>"}
# ❌ 失敗：{"TransCode": 0, "TransMsg": "Fail", "Data": ""}  → 見下方排查
```

**Node.js / TypeScript（npm install axios；crypto 為 Node.js 內建）**：

```typescript
import axios from 'axios';
import * as crypto from 'crypto';

const KEY = Buffer.from('pwFHCqoQZGmho4w6');  // 16 bytes
const IV  = Buffer.from('EkRm7iFT261dpevs');   // 16 bytes

function aesEncrypt(data: object): string {
    // aesUrlEncode（AES 專用）：encodeURIComponent + %20→+ + 補上 encodeURIComponent 不編碼的字元
    // ⚠️ 與 ecpayUrlEncode 不同：無 toLowerCase，無 .NET 替換
    const encoded = encodeURIComponent(JSON.stringify(data))
        .replace(/%20/g, '+')
        .replace(/~/g, '%7E')
        .replace(/!/g, '%21').replace(/'/g, '%27')
        .replace(/\(/g, '%28').replace(/\)/g, '%29').replace(/\*/g, '%2A');
    const cipher = crypto.createCipheriv('aes-128-cbc', KEY, IV);
    return Buffer.concat([cipher.update(encoded, 'utf8'), cipher.final()]).toString('base64');
}

(async () => {
    const ts = Math.floor(Date.now() / 1000);  // Unix 秒，不是毫秒
    const now = new Date();
    const pad = (n: number) => String(n).padStart(2, '0');
    const tradeDate = `${now.getFullYear()}/${pad(now.getMonth()+1)}/${pad(now.getDate())} ` +
                      `${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`;

    const resp = await axios.post(
        'https://ecpg-stage.ecpay.com.tw/Merchant/GetTokenbyTrade',
        {
            MerchantID: '3002607',
            RqHeader: { Timestamp: ts },
            Data: aesEncrypt({
                MerchantID: '3002607',                 // Data 內也需要
                RememberCard: 1, PaymentUIType: 2, ChoosePaymentList: '1',
                OrderInfo: {
                    MerchantTradeDate: tradeDate,
                    MerchantTradeNo: `precheck${ts}`,
                    TotalAmount: 100,
                    ReturnURL: 'https://example.com/notify',
                    TradeDesc: '預檢', ItemName: '預檢'
                },
                CardInfo: { Redeem: 0, OrderResultURL: 'https://example.com/result' },
                ConsumerInfo: {
                    MerchantMemberID: 'm1',      // ← RememberCard=1 時必填；=0 時可省略
                    Email: 't@t.com',            // ← Email 或 Phone 擇一必填
                    Phone: '0912345678',         // ← 選填（但 Email+Phone 都填更完整）
                    Name: '測試',               // ← 選填
                    CountryCode: '158'           // ← 選填（台灣）
                }
            })
        }
    );
    console.log(resp.data);
    // ✅ 成功：{ TransCode: 1, TransMsg: 'Success', Data: '<Base64字串>' }
    // ❌ 失敗：{ TransCode: 0, TransMsg: 'Fail', Data: '' }  → 見下方排查
})();
```

> **步驟 0 失敗排查**
>
> | 語言 | 症狀 | 最可能原因 | 解法 |
> |------|------|-----------|------|
> | 所有 | `TransCode: 0`，`TransMsg: "Fail"` | Key / IV 長度錯誤 | Key 和 IV 必須各為 **16 bytes**；Python: `len(KEY)==16`；Node.js: `Buffer.from(...).length==16` |
> | 所有 | `TransCode: 0`，`TransMsg: "Fail"` | Timestamp 用毫秒 | 必須用 **Unix 秒**（Python: `int(time.time())`；Node.js: `Math.floor(Date.now()/1000)`） |
> | 所有 | HTTP 404 | Domain 打錯 | URL 必須是 `ecpg-stage.ecpay.com.tw`，不是 `ecpayment-stage` |
> | 所有 | `TransCode: 0` 且加密確認無誤 | `ConsumerInfo` 物件缺失或 Email/Phone 皆未填 | `ConsumerInfo` 整體必填；至少要有 `Email` 或 `Phone` 其中一個 |
> | 所有 | `TransCode: 0` 且 `RememberCard=1` | `ConsumerInfo.MerchantMemberID` 未填 | `RememberCard=1` 時 `MerchantMemberID` 也必填；測試時可改 `RememberCard: 0` 省略此欄 |
> | Python | `TransCode: 0` | URL encode 方式錯誤 | 必須用 `quote_plus()`（不可用 `quote()`），且替換 `~` → `%7E` |
> | Node.js | `TransCode: 0` | `~` 未替換 | `encodeURIComponent` 不轉換 `~`，必須手動加 `.replace(/~/g, '%7E')` |
> | Node.js | `TransCode: 0` | `%20` 未替換為 `+` | `encodeURIComponent` 將空格編為 `%20`，需手動 `.replace(/%20/g, '+')` |
> | Node.js | `TransCode: 0` | 用了 `ecpayUrlEncode` 邏輯 | AES 加密前只用 `aesUrlEncode`（不做 `toLowerCase` 和 .NET 替換），勿混用 guides/13 的 CheckMacValue 實作 |

**確認 `TransCode: 1` 後，繼續步驟 1。**

---

#### 步驟 1：後端取得 Token

**目標**：呼叫 GetTokenbyTrade，回應解密後得到非空 `Token` 字串。

**端點**：`POST https://ecpg-stage.ecpay.com.tw/Merchant/GetTokenbyTrade`（注意：`ecpg-stage`，**不是** `ecpayment-stage`）

```php
use Ecpay\Sdk\Factories\Factory;
$factory = new Factory(['hashKey' => 'pwFHCqoQZGmho4w6', 'hashIv' => 'EkRm7iFT261dpevs']);
$postService = $factory->create('PostWithAesJsonResponseService');

$response = $postService->post([
    'MerchantID' => '3002607',
    'RqHeader'   => ['Timestamp' => time()],         // Unix 秒，不是毫秒
    'Data'       => [
        'MerchantID'        => '3002607',             // ← Data 內也要有 MerchantID（兩處都必填）
        'RememberCard'      => 1,
        'PaymentUIType'     => 2,
        'ChoosePaymentList' => '1',                   // 1=信用卡
        'OrderInfo' => [
            'MerchantTradeDate' => date('Y/m/d H:i:s'),
            'MerchantTradeNo'   => 'Test' . time(),   // 每次必須唯一
            'TotalAmount'       => 100,
            'ReturnURL'         => 'https://你的網站/ecpay/notify',
            'TradeDesc'         => '測試',
            'ItemName'          => '測試商品',
        ],
        'CardInfo'     => ['Redeem' => 0, 'OrderResultURL' => 'https://你的網站/ecpay/result'],
        'ConsumerInfo' => [
            'MerchantMemberID' => 'member001',  // ← RememberCard=1 時必填；=0 時可省略
            'Email'  => 'test@example.com',     // ← Email 或 Phone 擇一必填
            'Phone'  => '0912345678',           // ← 選填（同時填更完整）
            'Name'   => '測試',                 // ← 選填
            'CountryCode' => '158',             // ← 選填（台灣）
        ],
    ],
], 'https://ecpg-stage.ecpay.com.tw/Merchant/GetTokenbyTrade');

$token = $response['Data']['Token'] ?? null;
// ✅ 成功：$response['TransCode'] === 1 且 $token 為非空字串
```

> **⚠️ 步驟 1 失敗排查**
>
> | 症狀 | 最可能原因 | 解法 |
> |------|-----------|------|
> | HTTP 404 | URL 打到 `ecpayment-stage` | URL 必須是 `ecpg-stage.ecpay.com.tw/Merchant/GetTokenbyTrade` |
> | `TransCode` ≠ 1 | AES 加密失敗（非 PHP）或 Key/IV 值錯誤 | 非 PHP：先讀 [guides/14](./14-aes-encryption.md)；PHP：確認 HashKey/HashIV 完全一致（區分大小寫） |
> | `RtnCode` ≠ 1 | MerchantID 只填外層、參數格式錯誤 | 確認外層 `MerchantID` 與 `Data` 內層 `MerchantID` **兩處都存在** |
> | `RtnCode` ≠ 1 | `ConsumerInfo` 整體缺失或 Email/Phone 均未填 | `ConsumerInfo` 為必填 Object；`Email` 或 `Phone` 至少擇一填入 |
> | `RtnCode` ≠ 1 | `RememberCard=1` 但 `MerchantMemberID` 未填 | 啟用綁卡時 `MerchantMemberID` 為必填；測試時可改 `RememberCard: 0` |
> | `Token` 為空字串 | GetToken 業務層失敗 | 讀 `RtnMsg` 取得具體原因；常見：`MerchantTradeNo` 重複（每次呼叫必須唯一） |

> ✅ **步驟 1 成功時的預期輸出**
>
> HTTP 回應（外層）：`{"TransCode": 1, "TransMsg": "Success", "Data": "<Base64字串>"}`
>
> 解密 `Data` 後（內層）：`{"RtnCode": 1, "RtnMsg": "成功", "Token": "ecpay123..."}`
>
> 確認三項：`TransCode === 1` ✓ → `RtnCode === 1` ✓ → `Token` 為非空字串 ✓
>
> 若只有 `TransCode === 1` 但 `RtnCode !== 1`，讀 `RtnMsg` 查原因（最常見：`MerchantTradeNo` 重複）。

**Node.js / TypeScript 版本（步驟 1）：**

> 以下 `aesEncrypt` / `aesDecrypt` 函式可在步驟 4（CreatePayment）和步驟 5（Callback 解密）繼續複用。

```typescript
// npm install axios（或改用 fetch、node-fetch）
import axios from 'axios';
import * as crypto from 'crypto';

const HASH_KEY = Buffer.from('pwFHCqoQZGmho4w6');
const HASH_IV  = Buffer.from('EkRm7iFT261dpevs');

// aesUrlEncode（AES 專用）：只做 urlencode，不做 toLowerCase 和 .NET 字元替換
// 切勿與 CheckMacValue 的 ecpayUrlEncode 混用（guides/14 §對比表）
function aesEncrypt(data: object): string {
    const json = JSON.stringify(data);
    const encoded = encodeURIComponent(json)
        .replace(/%20/g, '+').replace(/~/g, '%7E')
        .replace(/!/g, '%21').replace(/'/g, '%27')
        .replace(/\(/g, '%28').replace(/\)/g, '%29').replace(/\*/g, '%2A');
    const cipher = crypto.createCipheriv('aes-128-cbc', HASH_KEY, HASH_IV);
    return Buffer.concat([cipher.update(encoded, 'utf8'), cipher.final()]).toString('base64');
}

export function aesDecrypt(base64: string): any {
    const decipher = crypto.createDecipheriv('aes-128-cbc', HASH_KEY, HASH_IV);
    const raw = Buffer.concat([decipher.update(base64, 'base64'), decipher.final()]).toString();
    return JSON.parse(decodeURIComponent(raw.replace(/\+/g, '%20')));
}

// Step 1：GetTokenbyTrade → 回傳 Token 字串
async function getEcpayToken(merchantTradeNo: string): Promise<string> {
    const pad = (n: number) => String(n).padStart(2, '0');
    const now = new Date();
    const tradeDate = `${now.getFullYear()}/${pad(now.getMonth()+1)}/${pad(now.getDate())} ${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`;

    const body = {
        MerchantID: '3002607',                               // ① 外層 MerchantID（必填）
        RqHeader: { Timestamp: Math.floor(Date.now() / 1000) }, // ② Unix 秒（不是毫秒）
        Data: aesEncrypt({
            MerchantID: '3002607',                           // ③ Data 內層也必填（兩處都要）
            RememberCard: 1,
            PaymentUIType: 2,
            ChoosePaymentList: '1',                          // '1'=信用卡
            OrderInfo: {
                MerchantTradeDate: tradeDate,                // 格式：'2026/03/12 10:00:00'
                MerchantTradeNo: merchantTradeNo,            // ④ 每次必須唯一
                TotalAmount: 100,
                ReturnURL: 'https://你的網站/ecpay/notify',
                TradeDesc: '測試',
                ItemName: '測試商品',
            },
            CardInfo: { Redeem: 0, OrderResultURL: 'https://你的網站/ecpay/result' },
            ConsumerInfo: {
                MerchantMemberID: 'member001',
                Email: 'test@example.com',
                Phone: '0912345678',
                Name: '測試',
                CountryCode: '158',
            },
        }),
    };

    const res = await axios.post(
        'https://ecpg-stage.ecpay.com.tw/Merchant/GetTokenbyTrade',  // ← ecpg，不是 ecpayment
        body, { headers: { 'Content-Type': 'application/json' } }
    );
    if (res.data.TransCode !== 1) throw new Error(`AES 層: ${res.data.TransMsg}`);
    const decoded = aesDecrypt(res.data.Data);
    if (decoded.RtnCode !== 1) throw new Error(`業務層: ${decoded.RtnMsg}`);
    return decoded.Token;  // ✅ 傳給前端 ECPay.createPayment()
}
```

---

#### 步驟 2：前端 JS SDK 渲染付款表單並取得 PayToken

**目標**：頁面出現信用卡號輸入欄位；消費者填卡後，透過 callback 取得 `PayToken`。

```html
<!-- 測試環境 SDK（正式環境改為 ecpg.ecpay.com.tw） -->
<div id="payment-form"><!-- JS SDK 在此渲染信用卡輸入表單 --></div>
<script src="https://ecpg-stage.ecpay.com.tw/scripts/sdk/ecpay.payment.js"></script>

<script>
  const _token = '{{ 步驟1後端傳入的Token }}';  // 純字串

  ECPay.createPayment({
    token: _token,
    containerId: 'payment-form',   // 渲染目標 div 的 id
    callback: function(result) {
      // 消費者填卡完成並點擊付款後觸發
      if (!result.Success) {
        console.error('取 PayToken 失敗:', result.ErrMsg);
        return;
      }
      console.log('PayToken 型別:', typeof result.PayToken);  // 應為 "string"
      submitToBackend({ payToken: result.PayToken, merchantTradeNo: '步驟1使用的訂單編號' });
    },
  });
</script>
```

> **⚠️ 步驟 2 失敗排查**
>
> | 症狀 | 最可能原因 | 解法 |
> |------|-----------|------|
> | 頁面無任何輸入框 | Token 無效或已逾時（預設 10 分鐘） | 回步驟 1 確認 Token 非空；縮短測試流程時間 |
> | Console CORS 錯誤 | 測試/正式環境 SDK URL 與 Token 不一致 | 確認 SDK URL 與 GetToken 端點環境完全一致 |
> | Console CSP 錯誤 | Content-Security-Policy 阻擋 | CSP header 加入 `https://ecpg-stage.ecpay.com.tw`（script-src、frame-src、connect-src） |
> | callback 未觸發 | 消費者尚未填卡並點擊付款 | 確認 containerId 目標 div 存在且可見 |

> ✅ **步驟 2 成功的視覺確認**
>
> 頁面出現 ECPay 付款表單，包含信用卡號、有效期（MM/YY）、安全碼三個輸入欄位。
>
> ⚠️ 若在步驟 1 取得 Token 後超過 **10 分鐘**才到步驟 2，表單會消失（Token 過期）。此時需回步驟 1，用**新的 MerchantTradeNo** 重新取得 Token。

---

#### 步驟 3：前端取得 PayToken

**目標**：消費者填入測試卡號後，步驟 2 的 `callback` 回傳 `result.PayToken`（非空字串）。

**測試卡號**：卡號 `4311-9522-2222-2222`，有效期大於當前月年（例如 `12/28`），安全碼 `222`，3D 驗證碼（測試環境固定）`1234`

> 在上方步驟 2 的 `callback` 中，`result.PayToken` 即為 PayToken（類型為 `string`）。
>
> 將 `result.PayToken` 連同步驟 1 使用的同一個 `merchantTradeNo` 一起送往後端，供步驟 4 使用。

> **⚠️ 步驟 3 失敗排查**
>
> | 症狀 | 最可能原因 | 解法 |
> |------|-----------|------|
> | `result.Success` 為 false | 卡號/有效期/安全碼格式錯誤 | 確認卡號、有效期（MM/YY）、安全碼 3 位數均已填入 |
> | 後端收到 `[object Object]` | 把 callback 結果物件整個傳過去 | 只傳 `result.PayToken` 字串，確認 `typeof result.PayToken === 'string'` |

> ✅ **步驟 3 成功時的預期輸出**
>
> `result.PayToken` 為非空字串（`typeof result.PayToken === 'string'` 為 `true`，長度通常 > 10 個字元）。
>
> ⚠️ PayToken 是**一次性**的——步驟 4（CreatePayment）呼叫後即消耗，不可重複使用。

---

#### 步驟 4：後端建立交易並判斷 ThreeDURL

**目標**：呼叫 CreatePayment，回應含 `ThreeDURL`（需導向 3D 驗證）或 `RtnCode=1`（直接成功）。

**端點**：`POST https://ecpg-stage.ecpay.com.tw/Merchant/CreatePayment`（仍是 `ecpg-stage`）

```php
$response = $postService->post([
    'MerchantID' => '3002607',
    'RqHeader'   => ['Timestamp' => time()],
    'Data'       => [
        'MerchantID'      => '3002607',
        'PayToken'        => $_POST['payToken'],           // 步驟 3 的 PayToken
        'MerchantTradeNo' => $_POST['merchantTradeNo'],    // 步驟 1 相同的訂單編號
    ],
], 'https://ecpg-stage.ecpay.com.tw/Merchant/CreatePayment');

$data = $response['Data'];  // PHP SDK 已自動解密

// ⚠️ 必須先判斷 ThreeDURL — 2025/8 起幾乎所有信用卡交易都會有
$threeDUrl = $data['ThreeDURL'] ?? '';
if ($threeDUrl !== '') {
    // 將 ThreeDURL 回傳給前端，前端執行跳轉
    echo json_encode(['threeDUrl' => $threeDUrl]);
} elseif (($data['RtnCode'] ?? null) === 1) {
    // 不需 3D 驗證，交易直接成功
    echo json_encode(['success' => true, 'tradeNo' => $data['TradeNo']]);
} else {
    echo json_encode(['error' => $data['RtnMsg'] ?? 'Unknown']);
}
```

**前端導向 3D 驗證**：
```javascript
const result = await response.json();
if (result.threeDUrl) {
    window.location.href = result.threeDUrl;  // ← 不可省略
} else if (result.success) {
    showSuccess();
}
```

> **⚠️ 步驟 4 失敗排查**
>
> | 症狀 | 最可能原因 | 解法 |
> |------|-----------|------|
> | HTTP 404 | CreatePayment 打到 `ecpayment-stage` | URL 必須是 `ecpg-stage.ecpay.com.tw/Merchant/CreatePayment` |
> | `TransCode` ≠ 1 | PayToken 過期或格式錯誤 | 確認步驟 3→4 間隔不超過 10 分鐘；PayToken 是純字串 |
> | `RtnCode` ≠ 1 且 `ThreeDURL` 空 | 卡片授權失敗 | 讀 `RtnMsg`；測試時確認使用測試卡號 `4311-9522-2222-2222` |
> | 交易建立後無任何反應、最終逾時 | **未處理 ThreeDURL**（最常見錯誤） | 加入 ThreeDURL 判斷並確認前端執行 `window.location.href` |

> ✅ **步驟 4 成功時的預期輸出（兩種情況擇一）**
>
> **情況 A（2025/8 後主要路徑）**：解密 `Data` 後，`ThreeDURL` 為非空字串 → 前端必須執行 `window.location.href = threeDUrl` 跳轉 3D 驗證頁面。此時 `RtnCode` 通常不是 1，這是**正常行為**，不是失敗。
>
> **情況 B（不需 3D 驗證）**：解密 `Data` 後，`ThreeDURL` 為空且 `RtnCode === 1` → 交易直接成功，`TradeNo` 為綠界訂單號。
>
> ⚠️ 只有 `ThreeDURL` 為空**且** `RtnCode !== 1` 時，才是真正的授權失敗，讀 `RtnMsg` 查原因。

**Node.js / TypeScript 版本（步驟 4）：**

```typescript
// Express 路由範例 — CreatePayment 後端端點
// 複用步驟 1 的 aesEncrypt / aesDecrypt
app.post('/ecpay/create-payment', express.json(), async (req, res) => {
    const { payToken, merchantTradeNo } = req.body;
    const body = {
        MerchantID: '3002607',
        RqHeader: { Timestamp: Math.floor(Date.now() / 1000) },
        Data: aesEncrypt({
            MerchantID: '3002607',
            PayToken: payToken,              // 步驟 3 前端回傳的 PayToken
            MerchantTradeNo: merchantTradeNo, // 步驟 1 使用的同一個訂單編號
        }),
    };

    const ecpayRes = await axios.post(
        'https://ecpg-stage.ecpay.com.tw/Merchant/CreatePayment',  // ← ecpg，不是 ecpayment
        body, { headers: { 'Content-Type': 'application/json' } }
    );
    if (ecpayRes.data.TransCode !== 1) {
        return res.status(200).json({ error: ecpayRes.data.TransMsg });
    }

    const data = aesDecrypt(ecpayRes.data.Data);

    // ⚠️ ThreeDURL 必須先判斷（2025/8 後幾乎必定進入此分支）
    if (data.ThreeDURL && data.ThreeDURL !== '') {
        return res.status(200).json({ threeDUrl: data.ThreeDURL });
    }
    if (data.RtnCode === 1) {  // RtnCode 是整數（AES-JSON 解密後）
        return res.status(200).json({ success: true, tradeNo: data.TradeNo });
    }
    return res.status(200).json({ error: data.RtnMsg });
});
```

---

#### 步驟 5：接收 Callback

**目標**：3D 驗證完成後，ReturnURL 與 OrderResultURL 都能正確接收並解密。

> **⚠️ ReturnURL 和 OrderResultURL 格式完全不同，解析方式絕對不可混用！**
>
> | Callback | 誰發送 | Content-Type | 讀取方式 | 必要回應 |
> |---------|-------|-------------|---------|---------|
> | **ReturnURL** | 綠界伺服器（S2S） | `application/json` | `file_get_contents('php://input')` → JSON body | 純文字 `1\|OK` |
> | **OrderResultURL** | 消費者瀏覽器（表單跳轉） | `application/x-www-form-urlencoded` | `$_POST['ResultData']` → `json_decode` → AES 解密 `Data` | 無需（顯示結果頁面） |

**ReturnURL 接收範例（PHP）**：
```php
// ← 讀 JSON body，不是 $_POST
$body = json_decode(file_get_contents('php://input'), true);
if (($body['TransCode'] ?? null) !== 1) {
    error_log('傳輸層錯誤: ' . ($body['TransMsg'] ?? ''));
    echo '1|OK';  // 即使出錯也要回應，否則綠界會重試
    exit;
}
$aesService = $factory->create(\Ecpay\Sdk\Services\AesService::class);
$data = $aesService->decrypt($body['Data']);
if ($data['RtnCode'] === 1) {
    // 更新訂單狀態
}
echo '1|OK';  // ← 純文字，不含 JSON、不含換行
```

**OrderResultURL 接收範例（PHP）**：
```php
// ⚠️ ResultData 是 JSON 字串，需先 json_decode 再 AES 解密 Data 欄位
$resultDataStr = $_POST['ResultData'] ?? '';
$outer = json_decode($resultDataStr, true);   // ← Step 1：JSON 解析外層結構
if (!$outer || ($outer['TransCode'] ?? 0) != 1) {
    echo '資料傳輸錯誤';
    exit;
}
$aesService = $factory->create(\Ecpay\Sdk\Services\AesService::class);
$data = $aesService->decrypt($outer['Data']); // ← Step 2：AES 解密 Data 欄位
// 顯示結果頁面（不需回應 1|OK）
echo $data['RtnCode'] === 1 ? '付款成功' : '付款失敗：' . $data['RtnMsg'];
```

> **⚠️ 步驟 5 失敗排查**
>
> | 症狀 | 最可能原因 | 解法 |
> |------|-----------|------|
> | ReturnURL 完全收不到通知 | URL 是 localhost 或非公開 IP | 使用 ngrok 或部署到有公開 IP 的主機 |
> | `php://input` 回傳空字串 | 用 `$_POST` 讀 JSON | ReturnURL 必須讀 `php://input`，不可用 `$_POST` |
> | OrderResultURL 解析失敗 | 直接 AES 解密 ResultData | `ResultData` 是 JSON 字串，先 `json_decode($str, true)` 取外層 `{TransCode, Data}`，再 AES 解密 `Data` 欄位 |
> | ReturnURL 不斷被重試 | 未回應 `1\|OK` | `echo '1|OK'`（純文字，即使出錯也必須回應） |

**Node.js / Express 版本（步驟 5）：**

```typescript
// ── ReturnURL（綠界伺服器 → 你的後端，JSON POST）──
// 複用步驟 1 的 aesDecrypt
app.post('/ecpay/notify', express.json(), async (req, res) => {
    const body = req.body;  // Content-Type: application/json，Express 已自動解析

    if (body.TransCode !== 1) {
        console.error('ECPay TransCode error:', body.TransMsg);
        // 即使出錯，HTTP 狀態必須是 200，body 必須是純文字 1|OK
        return res.status(200).type('text').send('1|OK');
    }

    const data = aesDecrypt(body.Data);
    if (data.RtnCode === 1) {
        await updateOrderStatus(data.MerchantTradeNo, 'paid');  // 更新訂單
    }
    res.status(200).type('text').send('1|OK');  // ← HTTP 200 + 純文字 1|OK（不含引號、換行）
});

// ── OrderResultURL（消費者瀏覽器 → 你的前端，Form POST）──
app.post('/ecpay/result', express.urlencoded({ extended: true }), (req, res) => {
    // ⚠️ ResultData 是 JSON 字串，需先 JSON.parse 取外層結構，再 AES 解密 Data
    const resultDataStr: string = req.body.ResultData;
    const outer = JSON.parse(resultDataStr);     // ← Step 1：JSON 解析外層 {TransCode, Data}
    if (outer.TransCode !== 1) {
        return res.send('<h1>資料傳輸錯誤</h1>');
    }
    const data = aesDecrypt(outer.Data);         // ← Step 2：AES 解密 Data 欄位

    if (data.RtnCode === 1) {  // RtnCode 是整數（AES-JSON 解密後）
        res.send(`<h1>付款成功！訂單：${data.MerchantTradeNo}</h1>`);
    } else {
        res.send(`<h1>付款失敗：${data.RtnMsg}</h1>`);
    }
    // ← 不需回應 1|OK，直接顯示結果頁面給消費者
});
```

> **ReturnURL vs OrderResultURL 關鍵差異（Node.js）**：
> - ReturnURL → `express.json()` → `req.body`（JSON 物件）→ 回應 `res.type('text').send('1|OK')`
> - OrderResultURL → `express.urlencoded()` → `req.body.ResultData`（字串）→ 回應 HTML 頁面
> 兩個 middleware 絕對不可互換。

> ✅ **步驟 5 成功時的預期接收**
>
> **ReturnURL**（S2S，綠界伺服器發送）：收到 `Content-Type: application/json` 的 POST，JSON body 的 `TransCode === 1`；解密 `Data` 後 `RtnCode === 1`，`MerchantTradeNo` 與步驟 1 完全一致。**你的端點必須回應純文字 `1|OK`（HTTP 200）。**
>
> **OrderResultURL**（瀏覽器跳轉，消費者發送）：收到 `Content-Type: application/x-www-form-urlencoded` 的 POST，表單欄位 `ResultData` 是 **JSON 字串**（含外層 `{TransCode, Data(AES)}`）；需先 `JSON.parse`/`json_decode`/`json.loads` 解析，確認 `TransCode === 1`，再 AES 解密 `Data`，確認 `RtnCode === 1`。**不需回應 `1|OK`，直接顯示結果頁面給消費者。**
>
> ⏱️ 兩個端點通常相差數秒到達，OrderResultURL（消費者瀏覽器）可能比 ReturnURL（伺服器）**更早**到達。業務邏輯（更新訂單、開發票）應以 ReturnURL 為準，OrderResultURL 僅用於呈現結果頁面。

**Python / Flask 版本（步驟 5）：**

```python
# pip install flask pycryptodome
import base64, json, urllib.parse
from Crypto.Cipher import AES
from Crypto.Util.Padding import unpad
from flask import Flask, request, jsonify

app = Flask(__name__)
KEY = b'pwFHCqoQZGmho4w6'
IV  = b'EkRm7iFT261dpevs'

def aes_decrypt(encrypted_base64: str) -> dict:
    raw = base64.b64decode(encrypted_base64)
    cipher = AES.new(KEY, AES.MODE_CBC, IV)
    decrypted = unpad(cipher.decrypt(raw), 16).decode('utf-8')
    return json.loads(urllib.parse.unquote_plus(decrypted))

# ── ReturnURL（綠界伺服器 → 你的後端，JSON POST）──
@app.route('/ecpay/notify', methods=['POST'])
def ecpay_notify():
    body = request.get_json()              # Content-Type: application/json
    if body.get('TransCode') != 1:
        return '1|OK', 200, {'Content-Type': 'text/plain'}  # 即使失敗也要回應
    data = aes_decrypt(body['Data'])
    if data.get('RtnCode') == 1:    # RtnCode 為整數（AES-JSON 解密後）
        pass  # 更新訂單狀態（update_order(data['MerchantTradeNo'], 'paid')）
    return '1|OK', 200, {'Content-Type': 'text/plain'}  # ← 純文字 1|OK，HTTP 200

# ── OrderResultURL（消費者瀏覽器 → 你的前端，Form POST）──
@app.route('/ecpay/result', methods=['POST'])
def ecpay_result():
    result_data = request.form.get('ResultData', '')  # ⚠️ 表單欄位，不是 JSON body
    # ⚠️ ResultData 是 JSON 字串，需先 json.loads 取外層，再 AES 解密 Data 欄位
    outer = json.loads(result_data)          # ← Step 1：JSON 解析外層 {TransCode, Data}
    if outer.get('TransCode') != 1:
        return '<h1>資料傳輸錯誤</h1>'
    data = aes_decrypt(outer['Data'])        # ← Step 2：AES 解密 Data 欄位
    if data.get('RtnCode') == 1:
        return f"<h1>付款成功！訂單：{data['MerchantTradeNo']}</h1>"
    return f"<h1>付款失敗：{data.get('RtnMsg', '未知錯誤')}</h1>"
    # ← 不需回應 1|OK，顯示結果頁面給消費者即可
```

> **Python ReturnURL vs OrderResultURL 關鍵差異**：
> - ReturnURL → `request.get_json()` → `body['Data']` AES 解密 → 回應 `'1|OK'`
> - OrderResultURL → `request.form['ResultData']` → `json.loads()` 取外層結構 → `aes_decrypt(outer['Data'])` → 回應 HTML 頁面
> 兩個路由的讀取方式**絕對不可互換**。

---

### 無公開 URL 時的測試替代方案

如果 ReturnURL 端點尚未準備好，可用 **QueryTrade 主動查詢替代 Callback 被動接收**：

1. GetToken 時 `ReturnURL` 填任意合法 HTTPS URL（例如 `https://example.com`）
2. 完成步驟 1–4，等待 30 秒
3. 呼叫 QueryTrade 主動查詢（**注意：QueryTrade 在 `ecpayment-stage`，不是 `ecpg-stage`**）：

```php
$response = $postService->post([
    'MerchantID' => '3002607',
    'RqHeader'   => ['Timestamp' => time()],
    'Data'       => [
        'PlatformID'      => '3002607',
        'MerchantID'      => '3002607',
        'MerchantTradeNo' => '你在步驟1使用的訂單編號',
    ],
], 'https://ecpayment-stage.ecpay.com.tw/1.0.0/Cashier/QueryTrade');
// $response['Data']['RtnCode'] === 1 → 交易成功
```

> **此方案不測試 Callback 邏輯**：確認付款流程正確後，再串接正式的 ReturnURL / OrderResultURL callback 邏輯。

---

## ⚡ 完整可執行範例（Python/Node.js）

> **目標**：複製貼上 → 安裝依賴 → 啟動 → 5 分鐘內完成第一筆測試交易。  
> 本節將所有程式碼片段整合為**可直接運行的單一檔案**。

### Python / Flask 版本

**安裝 & 啟動**

```bash
pip install flask pycryptodome requests
# 用 ngrok 或 Cloudflare Tunnel 建立公開 URL（ReturnURL 需可公開存取）
ngrok http 5000
# 將 ngrok 給你的 URL 設為環境變數後啟動
BASE_URL=https://xxxx.ngrok-free.app python app.py
```

**`app.py`（完整）**

```python
"""
站內付 2.0 完整範例 — Python Flask
測試帳號 MerchantID=3002607，僅限測試環境使用。
"""
import os, time, json, hmac, base64, urllib.parse
from flask import Flask, request, jsonify, render_template_string, abort
from Crypto.Cipher import AES
from Crypto.Util.Padding import pad, unpad
import requests as req

app = Flask(__name__)

# ── 測試帳號（勿用於正式環境）────────────────────────────────
MERCHANT_ID = '3002607'
HASH_KEY    = 'pwFHCqoQZGmho4w6'
HASH_IV     = 'EkRm7iFT261dpevs'

# ── 你的公開 URL（ngrok http 5000 後填入）───────────────────
BASE_URL   = os.getenv('BASE_URL', 'https://YOUR-NGROK-URL.ngrok-free.app')
RETURN_URL = f'{BASE_URL}/ecpay/callback'   # S2S JSON POST — 綠界 → 你的伺服器
RESULT_URL = f'{BASE_URL}/ecpay/result'     # 瀏覽器 Form POST — 消費者導向此頁

# ── 端點（測試環境）──────────────────────────────────────────
ECPG_URL      = 'https://ecpg-stage.ecpay.com.tw'       # GetToken, CreatePayment
ECPAYMENT_URL = 'https://ecpayment-stage.ecpay.com.tw'  # QueryTrade, DoAction

# ── AES-128-CBC 加/解密 ──────────────────────────────────────
def aes_encrypt(data: dict) -> str:
    # json_encode → quote_plus（aesUrlEncode：無 lowercase，無 .NET 替換）→ AES-128-CBC → base64
    json_str = json.dumps(data, separators=(',', ':'), ensure_ascii=False)
    plaintext = urllib.parse.quote_plus(json_str).replace('~', '%7E')
    cipher = AES.new(HASH_KEY.encode()[:16], AES.MODE_CBC, HASH_IV.encode()[:16])
    ct = cipher.encrypt(pad(plaintext.encode('utf-8'), 16))
    return base64.b64encode(ct).decode()

def aes_decrypt(cipher_b64: str) -> dict:
    ct = base64.b64decode(cipher_b64)
    cipher = AES.new(HASH_KEY.encode()[:16], AES.MODE_CBC, HASH_IV.encode()[:16])
    decrypted = unpad(cipher.decrypt(ct), 16).decode('utf-8')
    return json.loads(urllib.parse.unquote_plus(decrypted))

def post_to_ecpay(url: str, data: dict) -> dict:
    body = {
        'MerchantID': MERCHANT_ID,
        'RqHeader':   {'Timestamp': int(time.time())},  # 注意：只有 Timestamp，無 Revision
        'Data':       aes_encrypt(data),
    }
    r = req.post(url, json=body, timeout=10)
    r.raise_for_status()
    res = r.json()
    if res.get('TransCode') != 1:
        raise ValueError(f"ECPay TransMsg: {res.get('TransMsg', '未知錯誤')}")
    return aes_decrypt(res['Data'])

# ── 首頁：渲染付款 HTML（內嵌 JS SDK）───────────────────────
PAYMENT_HTML = '''<!DOCTYPE html>
<html lang="zh-TW">
<head>
  <meta charset="UTF-8">
  <title>站內付 2.0 測試</title>
  <!-- 步驟 2：載入綠界 JS SDK（測試環境） -->
  <script src="https://ecpg-stage.ecpay.com.tw/scripts/sdk/ecpay.payment.js"></script>
</head>
<body>
  <h1>站內付 2.0 — 完整範例</h1>
  <p>訂單：<span id="order-id"></span></p>
  <div id="payment-form"><!-- JS SDK 在此渲染信用卡輸入表單 --></div>
  <div id="status" style="margin-top:1em;color:#666;"></div>
  <script>
    // 每次頁面載入產生唯一的 MerchantTradeNo（時間戳確保不重複）
    const merchantTradeNo = 'Test' + Date.now();
    document.getElementById('order-id').textContent = merchantTradeNo;

    function setStatus(msg) { document.getElementById('status').textContent = msg; }

    // 步驟 1：後端取 Token
    setStatus('正在取得 Token…');
    fetch('/ecpay/gettoken', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ merchantTradeNo }),
    })
    .then(r => r.json())
    .then(({ token, error }) => {
      if (error) { setStatus('GetToken 失敗：' + error); return; }

      setStatus('Token 取得成功，載入付款表單…');
      // 步驟 2：JS SDK 渲染付款 UI
      ECPay.createPayment({
        token,
        containerId: 'payment-form',
        callback: function(result) {
          // 步驟 3：消費者填卡後，JS SDK 自動回呼並帶回 PayToken
          if (!result.Success) {
            setStatus('取 PayToken 失敗：' + result.ErrMsg);
            return;
          }
          setStatus('取得 PayToken，送出付款中…');
          // 步驟 4：後端呼叫 CreatePayment
          fetch('/ecpay/create_payment', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ payToken: result.PayToken, merchantTradeNo }),
          })
          .then(r => r.json())
          .then(({ threeDUrl, error }) => {
            if (error) { setStatus('付款失敗：' + error); return; }
            if (threeDUrl) {
              // 步驟 5a：有 ThreeDURL → 必須用 window.location.href（不可用 router.push）
              setStatus('導向 3D 驗證頁面…');
              window.location.href = threeDUrl;
            } else {
              setStatus('✅ 付款成功（無需 3D 驗證）！');
            }
          })
          .catch(err => setStatus('CreatePayment 失敗：' + err.message));
        }
      });
    })
    .catch(err => setStatus('GetToken 請求失敗：' + err.message));
  </script>
</body>
</html>'''

@app.route('/')
def index():
    return render_template_string(PAYMENT_HTML)

# ── 步驟 1：後端取 Token ─────────────────────────────────────
@app.route('/ecpay/gettoken', methods=['POST'])
def get_token():
    body = request.get_json()
    trade_no = body.get('merchantTradeNo', '')
    try:
        data = post_to_ecpay(f'{ECPG_URL}/Merchant/GetTokenbyTrade', {
            'MerchantID':        MERCHANT_ID,          # ⚠️ MerchantID 需在 Data 內再出現一次
            'RememberCard':      1,
            'PaymentUIType':     2,                    # 2=付款選擇清單頁
            'ChoosePaymentList': '1',                  # 1=信用卡一次付清
            'OrderInfo': {
                'MerchantTradeDate': time.strftime('%Y/%m/%d %H:%M:%S'),
                'MerchantTradeNo':   trade_no,
                'TotalAmount':       100,
                'ReturnURL':         RETURN_URL,
                'TradeDesc':         '測試商品',
                'ItemName':          '測試商品x1',
            },
            'CardInfo': {'Redeem': 0, 'OrderResultURL': RESULT_URL},
            'ConsumerInfo': {             # ⚠️ 必填：整個 Object 不可省略（RememberCard=0 時 Email/Phone 擇一；=1 時還需要 MerchantMemberID）
                'MerchantMemberID': 'member001',  # ← RememberCard=1 時必填；=0 時可省略
                'Email':  'test@example.com',     # ← Email 或 Phone 擇一必填
                'Phone':  '0912345678',
                'Name':   '測試',
                'CountryCode': '158',
            },
        })
        return jsonify({'token': data['Token']})
    except Exception as e:
        return jsonify({'error': str(e)}), 500

# ── 步驟 4：後端呼叫 CreatePayment ───────────────────────────
@app.route('/ecpay/create_payment', methods=['POST'])
def create_payment():
    body = request.get_json()
    try:
        data = post_to_ecpay(f'{ECPG_URL}/Merchant/CreatePayment', {
            'MerchantID':      MERCHANT_ID,
            'MerchantTradeNo': body['merchantTradeNo'],  # ⚠️ 必須與 GetToken 完全相同
            'PayToken':        body['payToken'],
        })
        # ⚠️ ThreeDURL 必須先判斷（2025/8 後幾乎必定進入此分支）
        three_d_url = data.get('ThreeDURL', '').strip()
        if three_d_url:
            return jsonify({'threeDUrl': three_d_url})
        rtn_code = int(data.get('RtnCode', 0))
        if rtn_code == 1:
            return jsonify({'success': True, 'tradeNo': data.get('TradeNo')})
        return jsonify({'error': f"付款失敗 ({rtn_code}): {data.get('RtnMsg', '未知')}"}), 400
    except Exception as e:
        return jsonify({'error': str(e)}), 500

# ── 步驟 5a：ReturnURL — 綠界 Server → 你的 Server（S2S JSON POST）─
@app.route('/ecpay/callback', methods=['POST'])
def callback():
    body = request.get_json(force=True)
    if not body:
        # 仍需回 1|OK + HTTP 200，否則 ECPay 重試
        return '1|OK', 200, {'Content-Type': 'text/plain'}
    # ⚠️ AES-JSON 雙層驗證：先查 TransCode（傳輸層），再查 RtnCode（業務層）
    if int(body.get('TransCode', 0)) != 1:
        print(f'[ReturnURL] ❌ 傳輸層錯誤 TransCode={body.get("TransCode")} TransMsg={body.get("TransMsg")}')
        return '1|OK', 200, {'Content-Type': 'text/plain'}
    data = aes_decrypt(body['Data'])
    rtn_code = int(data.get('RtnCode', 0))
    if rtn_code == 1:
        trade_no = data['MerchantTradeNo']
        print(f'[ReturnURL] ✅ 付款成功 訂單={trade_no}')
        # TODO: 在此更新資料庫訂單狀態為「已付款」
    else:
        print(f'[ReturnURL] ❌ 付款失敗 RtnCode={rtn_code} RtnMsg={data.get("RtnMsg")}')
    # ⚠️ 必須回應純文字 '1|OK'，不可回應 JSON 或 HTML
    return '1|OK', 200, {'Content-Type': 'text/plain'}

# ── 步驟 5b：OrderResultURL — 消費者瀏覽器 Form POST ─────────
@app.route('/ecpay/result', methods=['POST'])
def order_result():
    # ⚠️ ResultData 是 JSON 字串，需先 json.loads 取外層結構，再 AES 解密 Data
    cipher_text = request.form.get('ResultData', '')
    outer = json.loads(cipher_text)          # ← Step 1：JSON 解析外層 {TransCode, Data}
    if outer.get('TransCode') != 1:
        return '<h1>❌ 資料傳輸錯誤</h1>', 200
    data = aes_decrypt(outer['Data'])        # ← Step 2：AES 解密 Data 欄位
    if int(data.get('RtnCode', 0)) == 1:
        return f"<h1>✅ 付款成功！訂單：{data['MerchantTradeNo']}</h1>", 200
    return f"<h1>❌ 付款失敗：{data.get('RtnMsg', '未知錯誤')}</h1>", 200
    # ← 不需回應 '1|OK'，直接顯示結果頁面給消費者即可

if __name__ == '__main__':
    print(f'ReturnURL  = {RETURN_URL}')
    print(f'ResultURL  = {RESULT_URL}')
    app.run(debug=True, port=5000)
```

> ✅ **預期執行結果**：
> 1. 瀏覽 `http://localhost:5000` → 看到「正在取得 Token…」
> 2. 約 1 秒後出現信用卡輸入表單
> 3. 填入測試卡號 `4311952222222222`（CVC 任意三碼，有效期選未來日期）
> 4. 被導向 3D 驗證頁面（或直接顯示「付款成功」）
> 5. 驗證後終端機印出 `[ReturnURL] ✅ 付款成功 訂單=TestXXXXX`
> 6. 瀏覽器顯示「✅ 付款成功！訂單：TestXXXXX」

---

### Node.js / Express 版本

**安裝 & 啟動**

```bash
npm install express axios
BASE_URL=https://xxxx.ngrok-free.app node app.js
```

**`app.js`（完整）**

```javascript
/**
 * 站內付 2.0 完整範例 — Node.js / Express
 * 測試帳號 MerchantID=3002607，僅限測試環境使用。
 */
const express = require('express');
const axios   = require('axios');
const crypto  = require('crypto');
const qs      = require('querystring');

const app = express();
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// ── 測試帳號（勿用於正式環境）
const MERCHANT_ID = '3002607';
const HASH_KEY    = 'pwFHCqoQZGmho4w6';
const HASH_IV     = 'EkRm7iFT261dpevs';

const BASE_URL    = process.env.BASE_URL || 'https://YOUR-NGROK-URL.ngrok-free.app';
const RETURN_URL  = `${BASE_URL}/ecpay/callback`;
const RESULT_URL  = `${BASE_URL}/ecpay/result`;
const ECPG_URL    = 'https://ecpg-stage.ecpay.com.tw';

// ── AES-128-CBC 加/解密
function aesEncrypt(data) {
  const json = JSON.stringify(data);
  const encoded = encodeURIComponent(json).replace(/%20/g, '+').replace(/~/g, '%7E')
    .replace(/!/g, '%21').replace(/'/g, '%27')
    .replace(/\(/g, '%28').replace(/\)/g, '%29').replace(/\*/g, '%2A');
  const cipher = crypto.createCipheriv('aes-128-cbc', HASH_KEY, HASH_IV);
  cipher.setAutoPadding(true);
  return Buffer.concat([cipher.update(encoded, 'utf8'), cipher.final()]).toString('base64');
}
function aesDecrypt(cipherB64) {
  const ct = Buffer.from(cipherB64, 'base64');
  const decipher = crypto.createDecipheriv('aes-128-cbc', HASH_KEY, HASH_IV);
  const plain = Buffer.concat([decipher.update(ct), decipher.final()]).toString();
  return JSON.parse(decodeURIComponent(plain.replace(/\+/g, '%20')));
}
async function postToEcpay(url, data) {
  const body = {
    MerchantID: MERCHANT_ID,
    RqHeader:   { Timestamp: Math.floor(Date.now() / 1000) },  // 無 Revision
    Data:       aesEncrypt(data),
  };
  const res = await axios.post(url, body);
  if (res.data.TransCode !== 1) throw new Error(res.data.TransMsg || '未知錯誤');
  return aesDecrypt(res.data.Data);
}

// ── timing-safe 比對（防止 timing attack）
function safeEqual(a, b) {
  const bufA = Buffer.from(String(a)), bufB = Buffer.from(String(b));
  if (bufA.length !== bufB.length) return false;
  return crypto.timingSafeEqual(bufA, bufB);
}

// ── 首頁（與 Python 版同樣的 HTML，直接嵌入）
app.get('/', (req, res) => res.send(`<!DOCTYPE html>
<html lang="zh-TW"><head><meta charset="UTF-8"><title>站內付 2.0 測試</title>
<script src="https://ecpg-stage.ecpay.com.tw/scripts/sdk/ecpay.payment.js"></script>
</head><body>
<h1>站內付 2.0 — 完整範例（Node.js）</h1>
<p>訂單：<span id="order-id"></span></p>
<div id="payment-form"></div>
<div id="status" style="margin-top:1em;color:#666;"></div>
<script>
  const merchantTradeNo = 'Test' + Date.now();
  document.getElementById('order-id').textContent = merchantTradeNo;
  function setStatus(msg) { document.getElementById('status').textContent = msg; }
  setStatus('正在取得 Token…');
  fetch('/ecpay/gettoken', {
    method: 'POST', headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ merchantTradeNo }),
  }).then(r => r.json()).then(({ token, error }) => {
    if (error) { setStatus('GetToken 失敗：' + error); return; }
    setStatus('Token 取得，載入表單…');
    ECPay.createPayment({
      token, containerId: 'payment-form',
      callback: function(result) {
        if (!result.Success) { setStatus('PayToken 失敗：' + result.ErrMsg); return; }
        setStatus('送出付款中…');
        fetch('/ecpay/create_payment', {
          method: 'POST', headers: {'Content-Type':'application/json'},
          body: JSON.stringify({ payToken: result.PayToken, merchantTradeNo }),
        }).then(r => r.json()).then(({ threeDUrl, error }) => {
          if (error) { setStatus('付款失敗：' + error); return; }
          if (threeDUrl) { window.location.href = threeDUrl; }
          else { setStatus('✅ 付款成功！'); }
        });
      }
    });
  });
</script></body></html>`));

// ── 步驟 1：GetToken
app.post('/ecpay/gettoken', async (req, res) => {
  const { merchantTradeNo } = req.body;
  try {
    const now = new Date();
    const pad2 = n => String(n).padStart(2, '0');
    const tradeDate = `${now.getFullYear()}/${pad2(now.getMonth()+1)}/${pad2(now.getDate())} ${pad2(now.getHours())}:${pad2(now.getMinutes())}:${pad2(now.getSeconds())}`;
    const data = await postToEcpay(`${ECPG_URL}/Merchant/GetTokenbyTrade`, {
      MerchantID: MERCHANT_ID, RememberCard: 1,
      PaymentUIType: 2, ChoosePaymentList: '1',
      OrderInfo: {
        MerchantTradeDate: tradeDate, MerchantTradeNo: merchantTradeNo,
        TotalAmount: 100, ReturnURL: RETURN_URL,
        TradeDesc: '測試商品', ItemName: '測試商品x1',
      },
      CardInfo: { Redeem: 0, OrderResultURL: RESULT_URL },
      ConsumerInfo: {               // ⚠️ 必填：整個 Object 不可省略
        MerchantMemberID: 'member001',  // ← RememberCard=1 時必填；=0 時可省略
        Email: 'test@example.com',      // ← Email 或 Phone 擇一必填
        Phone: '0912345678',
        Name: '測試',
        CountryCode: '158',
      },
    });
    res.json({ token: data.Token });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

// ── 步驟 4：CreatePayment
app.post('/ecpay/create_payment', async (req, res) => {
  const { payToken, merchantTradeNo } = req.body;
  try {
    const data = await postToEcpay(`${ECPG_URL}/Merchant/CreatePayment`, {
      MerchantID: MERCHANT_ID,
      MerchantTradeNo: merchantTradeNo,  // ⚠️ 必須與 GetToken 完全相同
      PayToken: payToken,
    });
    // ⚠️ ThreeDURL 必須先判斷（2025/8 後幾乎必定進入此分支）
    const threeDUrl = data.ThreeDURL?.trim();
    if (threeDUrl) return res.json({ threeDUrl });
    const rtnCode = Number(data.RtnCode);
    if (rtnCode === 1) return res.json({ success: true, tradeNo: data.TradeNo });
    return res.status(400).json({ error: `付款失敗 (${rtnCode}): ${data.RtnMsg}` });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

// ── 步驟 5a：ReturnURL — S2S JSON POST
app.post('/ecpay/callback', (req, res) => {
  const body = req.body;
  if (!body) { return res.type('text').send('1|OK'); }  // 仍需回 1|OK 防止重試
  // ⚠️ AES-JSON 雙層驗證：先查 TransCode（傳輸層），再查 RtnCode（業務層）
  if (Number(body.TransCode) !== 1) {
    console.error('[ReturnURL] ❌ 傳輸層錯誤 TransCode=', body.TransCode);
    return res.type('text').send('1|OK');
  }
  const data = aesDecrypt(body.Data);
  // 註：safeEqual 用於 CheckMacValue 驗證時至關重要；RtnCode 非機密值，一般 === 比較即可
  if (safeEqual(data.RtnCode, 1)) {
    console.log('[ReturnURL] ✅ 付款成功 訂單=', data.MerchantTradeNo);
    // TODO: 更新資料庫訂單狀態為「已付款」
  } else {
    console.log('[ReturnURL] ❌ 付款失敗', data.RtnCode, data.RtnMsg);
  }
  res.type('text').send('1|OK');  // ⚠️ 必須回應純文字 '1|OK'
});

// ── 步驟 5b：OrderResultURL — 瀏覽器 Form POST
app.post('/ecpay/result', (req, res) => {
  // ⚠️ ResultData 是 JSON 字串，需先 JSON.parse 取外層結構，再 AES 解密 Data
  const outer = JSON.parse(req.body.ResultData || '{}');  // ← Step 1：JSON 解析
  if (outer.TransCode !== 1) {
    return res.send('<h1>❌ 資料傳輸錯誤</h1>');
  }
  const data = aesDecrypt(outer.Data);                    // ← Step 2：AES 解密
  // 註：RtnCode 非機密值，timing-safe 比對為可選（CheckMacValue 才是必須）
  if (safeEqual(data.RtnCode, 1))
    return res.send(`<h1>✅ 付款成功！訂單：${data.MerchantTradeNo}</h1>`);
  return res.send(`<h1>❌ 付款失敗：${data.RtnMsg || '未知錯誤'}</h1>`);
  // ← 不需回應 '1|OK'，直接顯示結果頁面給消費者
});

app.listen(5000, () => {
  console.log('🚀 Server: http://localhost:5000');
  console.log('ReturnURL =', RETURN_URL);
  console.log('ResultURL =', RESULT_URL);
});
```

### ⚡ ATM / CVS 完整可執行範例（Python Flask）

> **整合 ATM 虛擬帳號或超商代碼付款的開發者請複製此範例。**  
> 與信用卡最大差異：**跳過步驟 2（JS SDK）和步驟 3（getPayToken）**，GetToken 後直接 CreatePayment，回應中取出付款指示顯示給消費者。

```python
# pip install flask requests pycryptodome
# ATM/CVS 完整可執行範例 — 單一 Python 檔案，直接複製執行
import json, time, base64, urllib.parse
import requests
from flask import Flask, request, render_template_string
from Crypto.Cipher import AES
from Crypto.Util.Padding import pad, unpad

app = Flask(__name__)

MERCHANT_ID = '3002607'
HASH_KEY    = b'pwFHCqoQZGmho4w6'
HASH_IV     = b'EkRm7iFT261dpevs'
ECPG_URL    = 'https://ecpg-stage.ecpay.com.tw'

# ✅ 填入你的 ngrok 或可公開訪問的 URL（格式：https://xxxx.ngrok-free.app）
RETURN_URL = 'https://你的網域/ecpay/notify'   # Server-to-Server JSON POST（必填）

def aes_encrypt(data: dict) -> str:
    s = json.dumps(data, ensure_ascii=False, separators=(',', ':'))
    u = urllib.parse.quote_plus(s).replace('~', '%7E')
    cipher = AES.new(HASH_KEY, AES.MODE_CBC, HASH_IV)
    return base64.b64encode(cipher.encrypt(pad(u.encode('utf-8'), 16))).decode()

def aes_decrypt(base64_str: str) -> dict:
    cipher = AES.new(HASH_KEY, AES.MODE_CBC, HASH_IV)
    raw = unpad(cipher.decrypt(base64.b64decode(base64_str)), 16).decode('utf-8')
    return json.loads(urllib.parse.unquote_plus(raw))

def post_to_ecpay(url: str, data: dict) -> dict:
    body = {
        'MerchantID': MERCHANT_ID,
        'RqHeader': {'Timestamp': int(time.time())},
        'Data': aes_encrypt(data)
    }
    outer = requests.post(url, json=body).json()
    if outer.get('TransCode') != 1:
        raise RuntimeError(f"TransCode≠1: {outer}")
    inner = aes_decrypt(outer['Data'])
    if inner.get('RtnCode') != 1:
        raise RuntimeError(f"RtnCode≠1: {inner.get('RtnMsg')}")
    return inner

# ─────────────────────────── ATM 虛擬帳號取號 ───────────────────────────
@app.route('/payment/atm')
def atm_payment():
    trade_no = 'ATM' + str(int(time.time()))

    # 步驟 1：GetToken（ChoosePaymentList='3'=ATM；無需 JS SDK）
    token_data = post_to_ecpay(f'{ECPG_URL}/Merchant/GetTokenbyTrade', {
        'MerchantID':        MERCHANT_ID,
        'RememberCard':      0,
        'PaymentUIType':     2,
        'ChoosePaymentList': '3',        # 3 = ATM
        'OrderInfo': {
            'MerchantTradeDate': time.strftime('%Y/%m/%d %H:%M:%S'),
            'MerchantTradeNo':   trade_no,
            'TotalAmount':       100,
            'ReturnURL':         RETURN_URL,
            'TradeDesc':         '測試商品',
            'ItemName':          '測試商品x1',
        },
        'ATMInfo': {'ExpireDate': 3},    # 允許繳費天數（1~60，預設3）
        'ConsumerInfo': {                # ⚠️ 必填：即使 RememberCard=0，ConsumerInfo 仍需傳入
            'Email': 'test@example.com',  # ← Email 或 Phone 擇一必填
            'Phone': '0912345678',
            'Name':  '測試',
            'CountryCode': '158',
        },
    })
    token = token_data['Token']          # ← ATM 直接用此 Token，跳過 JS SDK 步驟 2、3

    # 步驟 4：CreatePayment（ATM 直接傳 Token，無 ThreeDURL 分支）
    pay_data = post_to_ecpay(f'{ECPG_URL}/Merchant/CreatePayment', {
        'MerchantID':      MERCHANT_ID,
        'MerchantTradeNo': trade_no,
        'PayToken':        token,        # ← 直接用 GetToken 回傳的 Token
    })
    # pay_data['RtnCode'] == 1 代表取號成功（不是付款成功）
    # pay_data['BankCode']  = '812'             ← 銀行代碼
    # pay_data['vAccount']  = '9103522850...'   ← 虛擬帳號
    # pay_data['ExpireDate'] = '2026/03/20'     ← 繳費期限
    # ⚠️ ReturnURL 不會立即觸發，消費者到 ATM 實際繳費後才由綠界推送

    # 步驟 5：顯示付款指示給消費者
    return render_template_string('''
        <h2>請在期限前完成 ATM 轉帳</h2>
        <table border="1" cellpadding="8">
          <tr><th>銀行代碼</th><td><b>{{ bank_code }}</b></td></tr>
          <tr><th>虛擬帳號</th><td><b>{{ vaccount }}</b></td></tr>
          <tr><th>繳費金額</th><td>NT$ 100</td></tr>
          <tr><th>繳費期限</th><td>{{ expire }}</td></tr>
          <tr><th>訂單號碼</th><td>{{ trade_no }}</td></tr>
        </table>
        <p><small>⚠️ 繳費完成後由綠界非同步通知 ReturnURL（可能需數分鐘至數天）</small></p>
    ''', bank_code=pay_data['BankCode'], vaccount=pay_data['vAccount'],
         expire=pay_data['ExpireDate'], trade_no=trade_no)

# ─────────────────────────── CVS 超商代碼取號 ───────────────────────────
@app.route('/payment/cvs')
def cvs_payment():
    trade_no = 'CVS' + str(int(time.time()))

    token_data = post_to_ecpay(f'{ECPG_URL}/Merchant/GetTokenbyTrade', {
        'MerchantID':        MERCHANT_ID,
        'RememberCard':      0,
        'PaymentUIType':     2,
        'ChoosePaymentList': '4',        # 4 = 超商代碼付款（CVS）
        'OrderInfo': {
            'MerchantTradeDate': time.strftime('%Y/%m/%d %H:%M:%S'),
            'MerchantTradeNo':   trade_no,
            'TotalAmount':       100,
            'ReturnURL':         RETURN_URL,
            'TradeDesc':         '測試商品',
            'ItemName':          '測試商品x1',
        },
        'CVSInfo': {'StoreExpireDate': 10080},  # 逾期分鐘數（預設10080=7天）
        'ConsumerInfo': {                # ⚠️ 必填：即使 RememberCard=0，ConsumerInfo 仍需傳入
            'Email': 'test@example.com',  # ← Email 或 Phone 擇一必填
            'Phone': '0912345678',
            'Name':  '測試',
            'CountryCode': '158',
        },
    })
    pay_data = post_to_ecpay(f'{ECPG_URL}/Merchant/CreatePayment', {
        'MerchantID':      MERCHANT_ID,
        'MerchantTradeNo': trade_no,
        'PayToken':        token_data['Token'],
    })
    # pay_data['PaymentNo']  = 'LLL22251222'    ← 超商繳費代碼
    # pay_data['ExpireDate'] = '2026/03/20 23:59:59'

    return render_template_string('''
        <h2>請到超商繳費</h2>
        <table border="1" cellpadding="8">
          <tr><th>超商代碼</th><td><b>{{ payment_no }}</b></td></tr>
          <tr><th>繳費金額</th><td>NT$ 100</td></tr>
          <tr><th>繳費期限</th><td>{{ expire }}</td></tr>
          <tr><th>訂單號碼</th><td>{{ trade_no }}</td></tr>
        </table>
        <p><small>⚠️ 繳費完成後由綠界非同步通知 ReturnURL（可能需數分鐘至數小時）</small></p>
    ''', payment_no=pay_data['PaymentNo'], expire=pay_data['ExpireDate'], trade_no=trade_no)

# ─────────────────── ReturnURL：接收綠界非同步繳費通知 ───────────────────
@app.route('/ecpay/notify', methods=['POST'])
def ecpay_notify():
    body   = request.get_json(force=True)
    # ⚠️ AES-JSON 雙層驗證：先查 TransCode（傳輸層），再解密 Data（業務層）
    if not body or int(body.get('TransCode', 0)) != 1:
        return '1|OK', 200, {'Content-Type': 'text/plain'}
    data   = aes_decrypt(body['Data'])        # AES 解密（格式與信用卡 ReturnURL 相同）
    rtn_code = int(data.get('RtnCode', 0))
    trade_no = data.get('MerchantTradeNo', '')
    pay_type = data.get('PaymentType', '')    # 例：'ATM_TAISHIN', 'CVS_CVS'

    if rtn_code == 1:
        # ✅ 消費者已完成繳費，更新訂單狀態為已付款
        print(f'[ReturnURL] ✅ 付款成功 訂單={trade_no} 方式={pay_type}')
        # TODO: db.update_order(trade_no, status='paid')
    else:
        print(f'[ReturnURL] ❌ 失敗 RtnCode={rtn_code} 訂單={trade_no}')

    return '1|OK', 200, {'Content-Type': 'text/plain'}   # 必須回傳此字串

if __name__ == '__main__':
    print(f'ATM 取號：http://localhost:5001/payment/atm')
    print(f'CVS 取號：http://localhost:5001/payment/cvs')
    print(f'ReturnURL：{RETURN_URL}')
    app.run(port=5001, debug=True)
```

> **測試 ATM/CVS ReturnURL**：測試環境不需要真正到 ATM/超商繳費。登入 `https://vendor-stage.ecpay.com.tw` → 訂單管理 → 找到你的訂單 → 「模擬付款」，即可觸發非同步 ReturnURL 通知。

---

## ATM / CVS 首次串接快速路徑

> **如果你整合的是 ATM 虛擬帳號或超商代碼付款（CVS），請從這裡開始。**  
> ATM/CVS 的主要差異在於：**不需要 JS SDK**、**CreatePayment 回應包含付款指示**、**ReturnURL 是非同步的**。

### ATM / CVS vs 信用卡流程對比

| 步驟 | 信用卡 | ATM / CVS |
|------|--------|-----------|
| 步驟 0（環境預檢） | 必做 | 必做 |
| 步驟 1（GetToken） | `ChoosePaymentList: '1'`（信用卡） | `ChoosePaymentList: '3'`（ATM）或 `'4'`（CVS） |
| 步驟 2（JS SDK） | **需要** — 渲染信用卡表單 | **不需要** — 跳過此步 |
| 步驟 3（getPayToken） | JS SDK callback 取得 | **不需要** — 跳過此步 |
| 步驟 4（CreatePayment） | `Token` = PayToken | `Token` = GetToken 回傳的 Token（直接用，不需 JS SDK 轉換） |
| 步驟 4 的回應 | `ThreeDURL`（導向 3D 驗證） | `BankCode + vAccount`（ATM）或 `PaymentNo`（CVS）— **顯示給消費者** |
| 步驟 5（ReturnURL 時機） | 3D 驗證完成後立即 | 消費者**實際到 ATM/超商繳費後**才觸發（可能數分鐘到數天後） |

### ATM 付款：GetToken 參數差異

```python
# ATM 付款的 GetToken（與信用卡相比，僅 ChoosePaymentList 不同）
data = post_to_ecpay(f'{ECPG_URL}/Merchant/GetTokenbyTrade', {
    'MerchantID':        MERCHANT_ID,
    'RememberCard':      0,
    'PaymentUIType':     2,
    'ChoosePaymentList': '3',   # 3=ATM
    'OrderInfo': {
        'MerchantTradeDate': time.strftime('%Y/%m/%d %H:%M:%S'),
        'MerchantTradeNo':   trade_no,
        'TotalAmount':       100,
        'ReturnURL':         RETURN_URL,
        'TradeDesc':         '測試商品',
        'ItemName':          '測試商品x1',
    },
    'ATMInfo': {'ExpireDate': 3},   # 允許繳費天數（1~60，預設3）
    'ConsumerInfo': {               # ⚠️ 必填：即使 RememberCard=0，ConsumerInfo 仍需傳入
        'Email': 'test@example.com',  # ← Email 或 Phone 擇一必填
        'Phone': '0912345678',
        'Name':  '測試',
        'CountryCode': '158',
    },
})
token = data['Token']  # 直接取 Token，無需 JS SDK 轉換
```

### ATM 付款：CreatePayment 參數（直接用 Token）

```python
# ATM CreatePayment：PayToken 直接填 GetToken 回傳的 Token 值（跳過 JS SDK 步驟）
data = post_to_ecpay(f'{ECPG_URL}/Merchant/CreatePayment', {
    'MerchantID':      MERCHANT_ID,
    'MerchantTradeNo': trade_no,
    'PayToken':        token,           # ← 直接用 GetToken 回傳的 Token
})
# 成功回應（RtnCode=1）包含：
# data['BankCode']  = '812'        ← 銀行代碼
# data['vAccount']  = '9103522850' ← 虛擬帳號（14天內有效，預設）
# data['ExpireDate'] = '2026/03/20' ← 繳費期限
```

### 顯示付款指示給消費者（ATM）

```python
@app.route('/payment/atm', methods=['POST'])
def atm_payment():
    trade_no = 'ATM' + str(int(time.time()))
    token_data = post_to_ecpay(f'{ECPG_URL}/Merchant/GetTokenbyTrade', {
        'MerchantID': MERCHANT_ID, 'RememberCard': 0,
        'PaymentUIType': 2, 'ChoosePaymentList': '3',  # 3=ATM
        'OrderInfo': {
            'MerchantTradeDate': time.strftime('%Y/%m/%d %H:%M:%S'),
            'MerchantTradeNo': trade_no, 'TotalAmount': 100,
            'ReturnURL': RETURN_URL, 'TradeDesc': '測試商品', 'ItemName': '測試商品x1',
        },
        'ATMInfo': {'ExpireDate': 3},
        'ConsumerInfo': {               # ⚠️ 必填：ConsumerInfo 不可省略
            'Email': 'test@example.com',  # ← Email 或 Phone 擇一必填
            'Phone': '0912345678',
            'Name':  '測試',
            'CountryCode': '158',
        },
    })
    pay_data = post_to_ecpay(f'{ECPG_URL}/Merchant/CreatePayment', {
        'MerchantID': MERCHANT_ID,
        'MerchantTradeNo': trade_no, 'PayToken': token_data['Token'],
    })
    # ⚠️ ReturnURL 不會立即觸發，消費者到 ATM 繳費後才觸發
    return render_template_string('''
        <h2>請在期限前完成 ATM 轉帳</h2>
        <p>銀行代碼：<b>{{ bank_code }}</b></p>
        <p>虛擬帳號：<b>{{ vaccount }}</b></p>
        <p>繳費金額：<b>NT${{ amount }}</b></p>
        <p>繳費期限：{{ expire }}</p>
        <p>訂單號碼：{{ trade_no }}</p>
        <p><small>繳費完成後頁面將自動更新（需刷新）</small></p>
    ''', bank_code=pay_data['BankCode'], vaccount=pay_data['vAccount'],
         amount='100', expire=pay_data['ExpireDate'], trade_no=trade_no)
```

### CVS 超商代碼：GetToken 和 CreatePayment 差異

```python
# CVS GetToken（與 ATM 的差異：ChoosePaymentList 和 Info 欄位不同）
'ChoosePaymentList': '4',         # 4=CVS 超商代碼（ATM 為 '3'）
'CVSInfo': {'StoreExpireDate': 10080},  # 逾期分鐘數（7天=10080分鐘）→ 替換 ATMInfo

# CVS CreatePayment（請求格式與 ATM 完全相同：MerchantID + MerchantTradeNo + PayToken）
# 回應包含：
# data['RtnCode']    = 1              ← 取號成功（非付款成功）
# data['PaymentNo']  = 'LLL22251222'  ← 超商繳費代碼（消費者在超商繳費時輸入）
# data['ExpireDate'] = '2026/03/20'   ← 繳費期限
```

### ATM / CVS ReturnURL：非同步接收

```python
@app.route('/ecpay/callback', methods=['POST'])
def callback():
    body = request.get_json(force=True)
    # ⚠️ AES-JSON 雙層驗證：先查 TransCode（傳輸層），再解密 Data（業務層）
    if not body or int(body.get('TransCode', 0)) != 1:
        return '1|OK', 200, {'Content-Type': 'text/plain'}
    data = aes_decrypt(body['Data'])
    rtn_code = int(data.get('RtnCode', 0))

    # ATM/CVS 的 ReturnURL 在消費者「繳款後」才觸發，RtnCode=1 代表實際付款成功
    if rtn_code == 1:
        trade_no = data['MerchantTradeNo']
        payment_type = data.get('PaymentType', '')   # 'ATM_TAISHIN', 'CVS_CVS' 等
        print(f'[ReturnURL] ✅ 已繳款 訂單={trade_no} 方式={payment_type}')
        # TODO: 更新訂單狀態為「已付款」，通知消費者繳費成功
    else:
        print(f'[ReturnURL] ❌ 繳款失敗 RtnCode={rtn_code}')
    return '1|OK', 200, {'Content-Type': 'text/plain'}
```

> ⚠️ **測試提醒**：測試環境的 ATM/CVS 付款，可在**綠界測試後台手動觸發 ReturnURL**，不需要真正到 ATM/超商繳費。  
> 路徑：登入 `https://vendor-stage.ecpay.com.tw` → 訂單管理 → 找到你的訂單 → 「模擬付款」。

---



> ⚠️ **若你整合的是 ATM、超商代碼（CVS）或超商條碼（Barcode），請務必讀完本節**。  
> ReturnURL **不會在 CreatePayment 之後立即觸發**，這是正常行為，不是 Bug。

### 信用卡 vs ATM/CVS 流程比較

| 流程階段 | 信用卡 | ATM / CVS / Barcode |
|----------|--------|---------------------|
| GetToken | 同 | 同 |
| JS SDK 取 PayToken | **需要**（消費者填卡 → JS callback → PayToken） | **不需要**（直接用 GetToken 回傳的 Token 作為 PayToken）|
| CreatePayment 回應 | Data 含 `ThreeDURL`，導引消費者做 3D 驗證 | Data 含**付款指示**（銀行代碼+虛擬帳號 或 超商代碼），**無** ThreeDURL |
| ReturnURL 觸發時機 | 3D 驗證完成後**立即**（通常數秒內） | 消費者**實際到 ATM / 超商完成繳款後**才送達，可能是數分鐘到數天後 |
| 你的頁面要做什麼 | 接收到 ThreeDURL 後導引消費者去驗證 | **解析 Data，顯示付款指示給消費者** |

### ATM 取號後的 CreatePayment 回應

ATM 付款的 CreatePayment 呼叫成功後，`Data` 解密後會包含：

```json
{
  "RtnCode": 1,
  "RtnMsg": "取號成功",
  "BankCode": "007",
  "vAccount": "92341234567890",
  "ExpireDate": "2025/12/31"
}
```

> 📌 精確欄位名稱與說明請透過 `web_fetch` 讀取 `references/Payment/站內付2.0API技術文件Web.md` 中「ATM」區段的官方 URL。

**你的後端必須做的事：**

1. AES 解密 `Data` → 取出 `BankCode`、`vAccount`、`ExpireDate`
2. 將這三個值存入資料庫（綁定訂單）
3. **回傳頁面給消費者，顯示虛擬帳號與繳費期限**
4. 等待 ReturnURL 的非同步通知

### CVS 超商代碼取號後的 CreatePayment 回應

CVS 付款成功後，`Data` 解密後包含：

```json
{
  "RtnCode": 1,
  "RtnMsg": "取號成功",
  "PaymentNo": "12345678901",
  "ExpireDate": "2025/12/31 23:59:59"
}
```

Barcode 則會包含 `Barcode1`、`Barcode2`、`Barcode3`（三段條碼）。

### 非同步 ReturnURL 的處理

- ReturnURL 觸發時機：消費者在 ATM 轉帳或去超商繳費之後，綠界系統確認收款後才發送
- 格式：**JSON POST**（與信用卡 ReturnURL 相同，需 AES 解密 `Data`）
- 解密後 `Data.RtnCode === 1` 代表付款成功

```php
// ReturnURL handler — ATM/CVS 和信用卡的格式完全相同
$data = $sdk->decryptData($request->Data);  // AES 解密
if ($data['RtnCode'] === 1) {  // ECPG Data 為 JSON 解密 → 整數，用 === 1
    // 訂單標記為已付款
}
echo '1|OK';  // 必須回傳此字串
```

### 若消費者遺失付款資訊

可用 `QueryPaymentInfo` 重新查詢（呼叫 `ecpayment` 網域的 Cashier/QueryPaymentInfo，非 `ecpg` 網域）：

```php
// 原始範例：scripts/SDK_PHP/example/Payment/Ecpg/QueryPaymentInfo.php
$input = [
    'MerchantID' => '3002607',
    'RqHeader'   => ['Timestamp' => time()],
    'Data'       => [
        'PlatformID'      => '3002607',   // 一般商店填 MerchantID；平台模式填平台商 ID
        'MerchantID'      => '3002607',
        'MerchantTradeNo' => '你的訂單號',
    ],
];
$response = $postService->post($input, 'https://ecpayment-stage.ecpay.com.tw/1.0.0/Cashier/QueryPaymentInfo');
// $response['Data'] 解密後包含 BankCode / vAccount 或 PaymentNo
```

> 📌 完整參數請參考 `references/Payment/站內付2.0API技術文件Web.md` → 查詢取號結果 URL。

### 測試注意事項

- 測試環境的 ATM/CVS 付款，綠界提供**模擬付款功能**，可在測試後台手動觸發 ReturnURL，不需要真正去 ATM/超商繳費。
- 測試時 ReturnURL 必須可公開存取（同信用卡流程）。若本機開發，使用 `ngrok` 或 `Cloudflare Tunnel` 建立臨時公開 URL。

---

## 🖥️ SPA / React / Vue / Next.js 整合架構

> **前後端分離框架常見陷阱**：ThreeDURL 必須用 `window.location.href` 導向，不可用前端 router（`router.push` / `<Link>` / `navigate()`），否則 3D 驗證頁面會被前端路由攔截而失效。

### 整體架構設計

```
                     ┌─────────────────────────────────────────────┐
你的前端（React/Vue）  │  你的後端 API                               │
                      │                                             │
[頁面載入]            │                                             │
  │ POST /api/ecpay/gettoken                                        │
  │──────────────────►│── GetTokenbyTrade ──►  ecpg-stage.ecpay    │
  │◄──────────────────│       { token }                             │
  │                   │                                             │
  │ ECPay.createPayment(token)  [JS SDK 直接與 ECPay 通訊]          │
  │ 消費者填卡 → getPayToken callback → PayToken                    │
  │                   │                                             │
  │ POST /api/ecpay/create_payment (payToken, merchantTradeNo)      │
  │──────────────────►│── CreatePayment ────►  ecpg-stage.ecpay    │
  │◄──────────────────│  { threeDUrl: "..." }                       │
  │                   │                                             │
  │ if (threeDUrl) window.location.href = threeDUrl  ← ⚠️ 必須這樣 │
  │ ← 不可用 router.push() 或 navigate() ←────────────────────     │
  │                   │                                             │
  │   [3D 驗證完成後瀏覽器被導向 OrderResultURL]                    │
  │                                                ReturnURL → │   │
  │                                             後端接收 1|OK   │   │
                     └─────────────────────────────────────────────┘
```

**關鍵規則**：
- ECPay JS SDK 腳本必須從**後端取得 Token 後**才呼叫 `createPayment`，Token 是每筆交易的一次性憑證
- **禁止前端直接呼叫 ecpg 端點**：API Key 必須在後端，前端只呼叫你自己的後端 API
- `ThreeDURL` 是 ECPay 的外部頁面，`window.location.href` 是唯一正確的導向方式

### React 完整範例（Hooks）

```jsx
// components/PaymentForm.jsx
import { useEffect, useState, useRef } from 'react';

const ECPAY_SDK_URL = 'https://ecpg-stage.ecpay.com.tw/scripts/sdk/ecpay.payment.js';

export default function PaymentForm({ amount = 100, onSuccess, onError }) {
  const [status, setStatus] = useState('初始化中…');
  const [token, setToken] = useState(null);
  const containerRef = useRef(null);
  // 每筆交易產生唯一 MerchantTradeNo，儲存在 ref 避免 closure 捕捉舊值
  const tradeNoRef = useRef('Test' + Date.now());

  // 步驟 1：載入 JS SDK 腳本（避免重複載入）
  useEffect(() => {
    if (document.getElementById('ecpay-sdk')) return;
    const script = document.createElement('script');
    script.id = 'ecpay-sdk';
    script.src = ECPAY_SDK_URL;
    script.onload = () => fetchToken();
    document.head.appendChild(script);
    return () => { /* 不移除腳本，避免後續訂單重新載入 */ };
  }, []);

  // 步驟 1b：向後端取 Token
  async function fetchToken() {
    setStatus('取得 Token 中…');
    try {
      const res = await fetch('/api/ecpay/gettoken', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ merchantTradeNo: tradeNoRef.current, amount }),
      });
      const { token, error } = await res.json();
      if (error) throw new Error(error);
      setToken(token);
    } catch (err) {
      setStatus('GetToken 失敗：' + err.message);
      onError?.(err);
    }
  }

  // 步驟 2：Token 就緒後渲染付款表單
  useEffect(() => {
    if (!token || !window.ECPay) return;
    setStatus('渲染付款表單…');
    window.ECPay.createPayment({
      token,
      containerId: 'ecpay-container',
      callback: async (result) => {
        // 步驟 3：取得 PayToken
        if (!result.Success) {
          setStatus('PayToken 失敗：' + result.ErrMsg);
          onError?.(new Error(result.ErrMsg));
          return;
        }
        setStatus('送出付款中…');
        try {
          // 步驟 4：後端 CreatePayment
          const res = await fetch('/api/ecpay/create_payment', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              payToken: result.PayToken,
              merchantTradeNo: tradeNoRef.current,
            }),
          });
          const { threeDUrl, error } = await res.json();
          if (error) throw new Error(error);

          if (threeDUrl) {
            // 步驟 5：⚠️ 必須用 window.location.href，不可用 router.push
            setStatus('導向 3D 驗證頁面…');
            window.location.href = threeDUrl;
          } else {
            setStatus('✅ 付款成功！');
            onSuccess?.({ merchantTradeNo: tradeNoRef.current });
          }
        } catch (err) {
          setStatus('CreatePayment 失敗：' + err.message);
          onError?.(err);
        }
      },
    });
  }, [token]);

  return (
    <div>
      <p style={{ color: '#666' }}>{status}</p>
      <div id="ecpay-container" ref={containerRef} />
    </div>
  );
}
```

### Next.js API Routes（後端）

```typescript
// app/api/ecpay/gettoken/route.ts
import { NextRequest, NextResponse } from 'next/server';
import { aesEncrypt, aesDecrypt, MERCHANT_ID } from '@/lib/ecpay';

export async function POST(req: NextRequest) {
  const { merchantTradeNo, amount } = await req.json();
  const now = new Date();
  const tradeDate = now.toLocaleString('zh-TW', {
    year: 'numeric', month: '2-digit', day: '2-digit',
    hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false,
  }).replace(/\//g, '/');

  const payload = {
    MerchantID: MERCHANT_ID, RememberCard: 1,
    PaymentUIType: 2, ChoosePaymentList: '1',
    OrderInfo: {
      MerchantTradeDate: tradeDate, MerchantTradeNo: merchantTradeNo,
      TotalAmount: amount, ReturnURL: `${process.env.BASE_URL}/api/ecpay/callback`,
      TradeDesc: '商品付款', ItemName: '商品x1',
    },
    CardInfo: { Redeem: 0, OrderResultURL: `${process.env.BASE_URL}/payment/result` },
    ConsumerInfo: {             // ⚠️ 必填：整個 Object 不可省略
      MerchantMemberID: 'member001',  // ← RememberCard=1 時必填；=0 時可省略
      Email: 'customer@example.com',  // ← Email 或 Phone 擇一必填
      Phone: '0912345678',
      Name: '顧客',
      CountryCode: '158',
    },
  };

  const body = {
    MerchantID: MERCHANT_ID,
    RqHeader: { Timestamp: Math.floor(Date.now() / 1000) },
    Data: aesEncrypt(payload),
  };
  const ecRes = await fetch('https://ecpg-stage.ecpay.com.tw/Merchant/GetTokenbyTrade', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  });
  const json = await ecRes.json();
  if (json.TransCode !== 1) return NextResponse.json({ error: json.TransMsg }, { status: 500 });
  const data = aesDecrypt(json.Data);
  return NextResponse.json({ token: data.Token });
}
```

```typescript
// app/api/ecpay/callback/route.ts  (ReturnURL — S2S JSON POST)
import { NextRequest, NextResponse } from 'next/server';
import { aesDecrypt } from '@/lib/ecpay';
import { db } from '@/lib/db';

export async function POST(req: NextRequest) {
  const body = await req.json();
  // ⚠️ AES-JSON 雙層驗證：先查 TransCode（傳輸層），再解密 Data（業務層）
  if (body.TransCode !== 1) {
    return new NextResponse('1|OK', { headers: { 'Content-Type': 'text/plain' } });
  }
  const data = aesDecrypt(body.Data);
  if (Number(data.RtnCode) === 1) {
    await db.order.update({
      where: { tradeNo: data.MerchantTradeNo },
      data: { status: 'paid', paidAt: new Date() },
    });
  }
  // ⚠️ 回應純文字 '1|OK'（不可是 JSON）
  return new NextResponse('1|OK', { headers: { 'Content-Type': 'text/plain' } });
}
```

```typescript
// app/payment/result/page.tsx  (OrderResultURL — 消費者瀏覽器 Form POST)
// Next.js App Router 不原生支援 Form POST 接收，建議用 Pages Router 或 Route Handler：
// app/api/ecpay/result/route.ts
export async function POST(req: NextRequest) {
  const formData = await req.formData();
  const resultDataStr = formData.get('ResultData') as string;
  // ⚠️ ResultData 是 JSON 字串，需先 JSON.parse 取外層結構，再 AES 解密 Data
  const outer = JSON.parse(resultDataStr);   // ← Step 1：JSON 解析外層 {TransCode, Data}
  if (outer.TransCode !== 1) {
    return new NextResponse('<h1>資料傳輸錯誤</h1>', { headers: { 'Content-Type': 'text/html' } });
  }
  const data = aesDecrypt(outer.Data);       // ← Step 2：AES 解密 Data 欄位
  // 重導到結果頁（帶查詢參數，讓前端渲染）
  const status = Number(data.RtnCode) === 1 ? 'success' : 'fail';
  const url = `/payment/result?status=${status}&tradeNo=${data.MerchantTradeNo}`;
  return NextResponse.redirect(new URL(url, req.url));
}
```

> ⚠️ **Next.js App Router 的 ThreeDURL 處理**：3D 驗證完成後，ECPay 會將消費者導向 `OrderResultURL`。若 `OrderResultURL` 是 Next.js API Route（接收 Form POST），需用 `redirect` 將消費者轉到前端頁面渲染結果，**不可直接在 Route Handler 回傳 HTML**（因 Response Content-Type 需為 HTML 且不受 Next.js Layout 包覆）。

### Vue 3 / Nuxt 3 快速整合

```vue
<!-- components/EcpayPayment.vue -->
<template>
  <div>
    <p class="status">{{ status }}</p>
    <div id="ecpay-container" />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
const props = defineProps({ amount: { type: Number, default: 100 } });
const emit = defineEmits(['success', 'error']);
const status = ref('初始化中…');
const tradeNo = 'Test' + Date.now();

onMounted(async () => {
  // 載入 ECPay SDK
  await loadScript('https://ecpg-stage.ecpay.com.tw/scripts/sdk/ecpay.payment.js');
  status.value = '取得 Token 中…';
  const res = await fetch('/api/ecpay/gettoken', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ merchantTradeNo: tradeNo, amount: props.amount }),
  });
  const { token, error } = await res.json();
  if (error) { status.value = '失敗：' + error; return; }

  window.ECPay.createPayment({
    token, containerId: 'ecpay-container',
    callback: async (result) => {
      if (!result.Success) { status.value = '失敗：' + result.ErrMsg; return; }
      const r = await fetch('/api/ecpay/create_payment', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ payToken: result.PayToken, merchantTradeNo: tradeNo }),
      });
      const { threeDUrl, error } = await r.json();
      if (error) { status.value = '付款失敗：' + error; return; }
      if (threeDUrl) {
        window.location.href = threeDUrl;  // ⚠️ 不可用 router.push 或 navigateTo
      } else {
        emit('success', { tradeNo });
      }
    },
  });
});

function loadScript(src) {
  return new Promise((resolve, reject) => {
    if (document.querySelector(`script[src="${src}"]`)) return resolve();
    const s = document.createElement('script');
    s.src = src; s.onload = resolve; s.onerror = reject;
    document.head.appendChild(s);
  });
}
</script>
```

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
    "CardInfo": {"Redeem": 0, "OrderResultURL": "https://yourdomain.com/ecpay/result"},
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

def create_payment(pay_token: str, merchant_trade_no: str) -> dict:
    """步驟 4：傳入步驟 3 的 PayToken 與步驟 1 相同的 MerchantTradeNo"""
    body = {
        "MerchantID": "3002607",
        "RqHeader": {"Timestamp": int(time.time())},
        "Data": aes_encrypt({               # 複用步驟 0 / 步驟 1 的 aes_encrypt
            "MerchantID": "3002607",        # Data 內也必須有 MerchantID
            "PayToken": pay_token,
            "MerchantTradeNo": merchant_trade_no,  # ← 必須與 GetTokenbyTrade 完全相同
        })
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

# ⚠️ 必須先判斷 ThreeDURL（2025/8 後幾乎必定出現）
three_d_url = data.get('ThreeDURL', '')
if three_d_url:
    # 將 ThreeDURL 傳給前端，前端執行跳轉
    # return jsonify({"threeDUrl": three_d_url})
    print(f"前端必須跳轉至: {three_d_url}")
elif data.get('RtnCode') == 1:
    # 不需 3D 驗證，交易直接成功
    print(f"交易成功，TradeNo: {data.get('TradeNo')}")
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
        pass  # TODO: 更新訂單 update_order(data['MerchantTradeNo'], 'paid')
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
    if data.get('RtnCode') == 1:
        return f"<h1>付款成功！訂單：{data.get('MerchantTradeNo')}</h1>"
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
            'Redeem'            => 0,
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

> 🔍 **此步驟失敗？** TransCode ≠ 1 → 排查見 [§15 TransCode 診斷](15-troubleshooting.md#15-站內付20-transcode--1-診斷流程)；HTTP 404 → 確認 Domain 為 `ecpg-stage.ecpay.com.tw`（非 `ecpayment`）。

### 前端 JavaScript SDK 整合

後端 GetToken 呼叫取得 Token 後，在前端使用 ECPay JavaScript SDK：

```
> 原始範例：scripts/SDK_PHP/example/Payment/Ecpg/CreateCreditOrder/WebJS.html
```

**1. 引入 ECPay JavaScript SDK**
```html
<!-- 測試環境 -->
<script src="https://ecpg-stage.ecpay.com.tw/scripts/sdk/ecpay.payment.js"></script>
<!-- 正式環境 -->
<script src="https://ecpg.ecpay.com.tw/scripts/sdk/ecpay.payment.js"></script>
```

> **JS SDK 版本**：上方 `ecpay.payment.js` 為 ECPay 站內付2.0 WEB JS SDK。ECPay 可能更新 SDK 版本或路徑，
> 請以[綠界站內付官方文件](https://developers.ecpay.com.tw/)中的最新版本路徑為準。

> **CSP（Content Security Policy）設定**：若你的網站啟用了 CSP header，需允許 ECPay domain：
> - `script-src`: 加入 `https://ecpg.ecpay.com.tw`（正式）或 `https://ecpg-stage.ecpay.com.tw`（測試）
> - `frame-src`: 同上（SDK 會使用 iframe 渲染付款表單）
> - `connect-src`: 同上（SDK 內部 API 呼叫）

**2. 初始化 SDK**

> **API 呼叫風格說明**：本指南的快速入門範例使用 object-based 風格（`ECPay.createPayment({ token, containerId, callback })`），
> 而下方「JS SDK 官方 API 參考」區段則對照官方文件的 positional 風格（`ECPay.createPayment(Token, Language, callBack, Version)`）。
> 兩者功能相同，以[官方文件](https://developers.ecpay.com.tw/9003.md)及 [GitHub 範例](https://github.com/ECPay/ECPayPaymentGatewayKit_Web)為準。

```javascript
// envi: 0=正式, 1=測試
// type: 1=Web
ECPay.initialize(envi, 1, function(errMsg) {
    console.error('SDK 初始化失敗:', errMsg);
});
```

**3. 渲染付款 UI**
```javascript
// _token: 後端 GetTokenbyTrade 取得的 Token
// language: 'zh-TW', 'en-US', etc.
ECPay.createPayment(_token, language, function(errMsg) {
    console.error('建立付款 UI 失敗:', errMsg);
}, 'V2');
```

**4. 取得 PayToken（消費者填完付款資訊後）**
```javascript
ECPay.getPayToken(function(payToken, errMsg) {
    if (errMsg) {
        console.error('取得 PayToken 失敗:', errMsg);
        return;
    }
    // 將 payToken 送到後端建立交易
    submitPayment(payToken);
});
```

> ⚠️ **常見陷阱：payToken 是字串**
> `getPayToken` 回呼的第一個參數 `payToken` 是**純字串**（如 `"a1b2c3d4..."`）。
> 常見錯誤是將整個回呼物件或包裝過的結構送往後端，導致後端收到 `[object Object]` 而非 Token 字串。
> 確認送往後端的值為 `typeof payToken === 'string'`。

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

> 🔍 **此步驟失敗？** ①確認 `TransCode == 1`；② 解密 `Data` 後若 `ThreeDURL` 非空，**必須導向該 URL** 完成 3D 驗證，否則交易逾時失敗；③完整排查見 [§16 3D Secure 處理](15-troubleshooting.md#16-站內付20-3d-secure-處理遺漏)。

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

// 先檢查 TransCode 確認 API 是否成功
$transCode = $jsonBody['TransCode'] ?? null;
if ($transCode != 1) {
    error_log('ECPay TransCode Error: ' . ($jsonBody['TransMsg'] ?? 'unknown'));
}

// 解密 Data 取得交易細節
$decryptedData = $aesService->decrypt($jsonBody['Data']);
// $decryptedData 包含：RtnCode, RtnMsg, MerchantID, Token, TokenExpireDate 等

// 回應 1|OK（官方規格 9058.md）
echo '1|OK';
```

> 🔍 **此步驟失敗？** OrderResultURL 最常見錯誤：直接對 `ResultData` AES 解密（需先 `json_decode` 取出外層 `{TransCode, Data}`，再解密 `Data`）；ReturnURL 忘記回應 `1|OK`；AES 解密失敗請確認 Key/IV 取自 `HashKey`/`HashIV`（非 AIO 的 HashKey）。詳細排查見 [§17 Callback 格式混淆](15-troubleshooting.md#17-站內付20-callback-格式混淆)。

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
| TradeNo | String | ECPay 交易編號 |
| MerchantTradeNo | String | 特店訂單編號 |

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

```json
{
  "RtnCode": 1,
  "RtnMsg": "交易成功",
  "TradeNo": "2026031215360001",
  "MerchantTradeNo": "test1741830960",
  "TradeAmt": 100,
  "PaymentDate": "2026/03/12 15:36:00",
  "PaymentType": "Credit_CreditCard",
  "ThreeDURL": ""
}
```

> `ThreeDURL` 為空字串或不存在時，代表交易已完成，直接回應成功。

#### CreatePayment 成功回應 — 情境 B：需 3D 驗證（Data 解密後）

```json
{
  "RtnCode": 0,
  "RtnMsg": "Redirect to 3D",
  "TradeNo": "2026031215360002",
  "MerchantTradeNo": "test1741830961",
  "ThreeDURL": "https://payment-stage.ecpay.com.tw/Cashier/3DVerify?tk=abc123..."
}
```

> `ThreeDURL` 非空時，代表需要 3D 驗證，**此時 `RtnCode` 不是 1 也是正常的**。
> 必須將用戶重導向 `ThreeDURL`，不要誤判為失敗。

#### 3D Secure 驗證跳轉（必處理）

> ⚠️ **自 2025/8 起 3D Secure 2.0 已強制實施，幾乎所有信用卡交易的 CreatePayment 回應都會包含 `ThreeDURL`。生成代碼時此分支不可省略。**

CreatePayment 的 Data 解密後，若含 `ThreeDURL` 欄位（非空字串），代表此筆交易需要 3D 驗證。**前端必須將消費者導向該 URL 完成驗證**，否則交易將逾時失敗。

```javascript
// 後端 CreatePayment 回應解密後回傳給前端
const result = await response.json();

if (result.ThreeDURL && result.ThreeDURL !== '') {
    // 必須跳轉至 3D 驗證頁面（2025/8後幾乎必定進入此分支）
    window.location.href = result.ThreeDURL;
} else if (result.RtnCode === 1) {
    // 不需 3D 驗證，交易直接成功（RtnCode 是整數 1，非字串 "1"）
    showSuccess(result);
} else {
    showError(result.RtnMsg);
}
```

> **注意**：3D 驗證完成後，綠界會將結果 POST 至 OrderResultURL（前端顯示）和 ReturnURL（後端通知），流程與一般付款回呼相同。

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

**注意**：站內付2.0 的請款/退款端點在 `ecpayment-stage.ecpay.com.tw`，不是 `ecpg-stage`。

```php
$input = [
    'MerchantID' => '3002607',
    'RqHeader'   => ['Timestamp' => time()],
    'Data'       => [
        'PlatformID'      => '3002607',  // 一般商店填 MerchantID；平台模式填平台商 ID
        'MerchantID'      => '3002607',
        'MerchantTradeNo' => '你的訂單編號',
        'TradeNo'         => '綠界交易編號',
        'Action'          => 'C',  // C=請款, R=退款, E=取消, N=放棄
        'TotalAmount'     => 100,
    ],
];
$response = $postService->post($input, 'https://ecpayment-stage.ecpay.com.tw/1.0.0/Credit/DoAction');
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
        'PlatformID'      => '3002607',  // 一般商店填 MerchantID；平台模式填平台商 ID
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
        'PlatformID'      => '3002607',  // 一般商店填 MerchantID；平台模式填平台商 ID
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

## Web vs App 整合差異

站內付2.0 支援 Web 和 App 兩種整合方式：

| 面向 | Web | App (iOS/Android) |
|------|-----|-------------------|
| 取 Token 方式 | JavaScript SDK | 原生 SDK (ECPayPaymentGatewayKit) |
| 付款 UI | Web 頁面中的 iframe 或嵌入式元件 | 原生 SDK 提供的付款畫面 |
| 後端 API | 完全相同 | 完全相同 |
| GetToken 端點 | 相同 | 相同 |
| CreatePayment | 相同 | 相同 |

### 原生 SDK vs WebView 方案比較

| 比較項目 | 原生 SDK（ECPayPaymentGatewayKit） | WebView 嵌入 |
|---------|----------------------------------|-------------|
| 付款體驗 | 原生 UI，體驗最佳 | 網頁嵌入，體驗次之 |
| 開發成本 | 需整合原生 SDK，iOS/Android 各一份 | 共用 Web 付款頁面，開發量較少 |
| 維護成本 | SDK 版本升級需重新發布 App | Web 端更新即可，無需發布 App |
| Apple Pay 支援 | 完整支援（需原生 SDK） | 不支援 |
| 3D Secure | SDK 內建處理 | 需自行處理 WebView 導向 |
| 適用場景 | 重視付款體驗、需要 Apple Pay | 快速上線、跨平台共用 |

> **建議**：如需 Apple Pay 或追求最佳付款體驗，使用原生 SDK；如需快速上線或以 React Native / Flutter 開發，使用 WebView 方案。

### iOS 原生 SDK 初始化概要

> 官方文件：`references/Payment/站內付2.0API技術文件App.md` — iOS APP SDK / 初始化、使用說明

**1. 安裝 SDK**

透過 CocoaPods 或手動匯入 `ECPayPaymentGatewayKit.framework`：

```ruby
# Podfile（如官方提供 CocoaPods 支援）
pod 'ECPayPaymentGatewayKit'
```

**2. 初始化 SDK**

```swift
import ECPayPaymentGatewayKit

// 建立 SDK 實例
// serverType: .Stage（測試）或 .Prod（正式）
let ecpaySDK = ECPayPaymentGatewayManager(
    serverType: .Stage,
    merchantID: "3002607"
)
```

**3. 取得付款畫面**

```swift
// token: 從後端 GetTokenbyTrade API 取得
ecpaySDK.createPayment(token: token, language: "zh-TW") { result in
    switch result {
    case .success(let payToken):
        // 將 payToken 送到後端呼叫 CreatePayment
        self.submitPayment(payToken: payToken)
    case .failure(let error):
        print("付款失敗: \(error.localizedDescription)")
    }
}
```

**4. 自訂 Title Bar 顏色**（選用）

```swift
ecpaySDK.setTitleBarColor(UIColor(red: 0.0, green: 0.5, blue: 0.3, alpha: 1.0))
```

### Android 原生 SDK 初始化概要

> 官方文件：`references/Payment/站內付2.0API技術文件App.md` — Android APP SDK / 初始化、使用說明

**1. 安裝 SDK**

在 `build.gradle` 加入 ECPay SDK 依賴（或手動匯入 .aar 檔案）：

```groovy
dependencies {
    implementation files('libs/ECPayPaymentGatewayKit.aar')
}
```

**2. 初始化 SDK**

```kotlin
import com.ecpay.paymentgatewaykit.ECPayPaymentGatewayManager

// serverType: ServerType.Stage（測試）或 ServerType.Prod（正式）
val ecpaySDK = ECPayPaymentGatewayManager(
    context = this,
    serverType = ServerType.Stage,
    merchantID = "3002607"
)
```

**3. 取得付款畫面**

```kotlin
// token: 從後端 GetTokenbyTrade API 取得
ecpaySDK.createPayment(token = token, language = "zh-TW") { result ->
    if (result.isSuccess) {
        val payToken = result.payToken
        // 將 payToken 送到後端呼叫 CreatePayment
        submitPayment(payToken)
    } else {
        Log.e("ECPay", "付款失敗: ${result.errorMessage}")
    }
}
```

**4. 自訂 Title Bar 顏色**（選用）

> 官方文件：`references/Payment/站內付2.0API技術文件App.md` — Android APP SDK / 修改Title bar顏色

```kotlin
ecpaySDK.setTitleBarColor(Color.parseColor("#008040"))
```

**5. 設定畫面方向**（選用）

```kotlin
ecpaySDK.setScreenOrientation(ActivityInfo.SCREEN_ORIENTATION_PORTRAIT)
```

### Apple Pay 前置準備

> 官方文件：`references/Payment/站內付2.0API技術文件App.md` — 準備事項 / Apple Pay開發者前置準備說明

使用 Apple Pay 付款需完成以下前置作業：

| 步驟 | 說明 |
|------|------|
| 1. Apple Developer 帳號 | 需擁有付費的 Apple Developer Program 帳號 |
| 2. Merchant ID 註冊 | 在 Apple Developer 後台建立 Merchant ID |
| 3. 憑證申請 | 產生 Payment Processing Certificate 並提供給綠界 |
| 4. Xcode 設定 | 在 Xcode 專案的 Capabilities 啟用 Apple Pay 並綁定 Merchant ID |
| 5. 綠界後台設定 | 在綠界商戶後台啟用 Apple Pay 並上傳憑證 |
| 6. 域名驗證 | 將 Apple 提供的驗證檔案放在你的網站根目錄 |

> **注意**：Apple Pay 僅支援 iOS 原生 SDK 方式整合，WebView 方案不支援 Apple Pay。GetToken 時 `ChoosePaymentList` 須帶 `"7"`。

> **iOS Apple Pay 進階**：如需自訂 Apple Pay 付款體驗或延遲付款授權，
> 請參閱官方文件 `references/Payment/站內付2.0API技術文件App.md` 中的 Apple Pay 專區。

### App 端整合流程

1. **iOS**：透過 CocoaPods 或手動匯入整合 ECPay SDK
2. **Android**：透過 Gradle 依賴或手動匯入 .aar 整合 ECPay SDK
3. App 呼叫原生 SDK 的 `createPayment` 方法，傳入 Token（從後端 GetTokenbyTrade 取得）
4. 消費者在原生付款畫面完成付款
5. SDK 回傳 `PayToken` 給 App
6. App 將 `PayToken` 送到後端，呼叫 `CreatePayment`（與 Web 相同）

### App 專屬注意事項

- App 端的 `OrderResultURL` 需設定為可被 App 攔截的 URL scheme 或 Universal Link
- 3D Secure 驗證在 App 中會開啟 WebView
- 測試時需使用實機，模擬器可能無法完整測試付款流程
- Apple Pay 僅支援 iOS 原生 SDK，不支援 WebView 或 Android

### iOS (Swift) WebView 整合範例

```swift
import WebKit

class PaymentViewController: UIViewController, WKNavigationDelegate {
    var webView: WKWebView!

    override func viewDidLoad() {
        super.viewDidLoad()
        let config = WKWebViewConfiguration()
        webView = WKWebView(frame: view.bounds, configuration: config)
        webView.navigationDelegate = self
        view.addSubview(webView)

        // 從後端取得站內付2.0 Token 後，載入付款頁面
        if let url = URL(string: "https://你的後端/ecpg/payment-page?token=\(payToken)") {
            webView.load(URLRequest(url: url))
        }
    }

    func webView(_ webView: WKWebView, decidePolicyFor navigationAction: WKNavigationAction,
                 decisionHandler: @escaping (WKNavigationActionPolicy) -> Void) {
        // 攔截付款完成的回呼 URL
        if let url = navigationAction.request.url,
           url.host == "你的網站" && url.path.contains("/payment/complete") {
            handlePaymentResult(url: url)
            decisionHandler(.cancel)
            return
        }
        decisionHandler(.allow)
    }
}
```

### Android (Kotlin) WebView 整合範例

```kotlin
class PaymentActivity : AppCompatActivity() {
    private lateinit var webView: WebView

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_payment)

        webView = findViewById(R.id.paymentWebView)
        webView.settings.javaScriptEnabled = true
        webView.settings.domStorageEnabled = true

        webView.webViewClient = object : WebViewClient() {
            override fun shouldOverrideUrlLoading(view: WebView?, request: WebResourceRequest?): Boolean {
                val url = request?.url?.toString() ?: return false
                // 攔截付款完成的回呼 URL
                if (url.contains("/payment/complete")) {
                    handlePaymentResult(url)
                    return true
                }
                return false
            }
        }

        // 從後端取得站內付2.0 Token 後，載入付款頁面
        val payToken = intent.getStringExtra("payToken")
        webView.loadUrl("https://你的後端/ecpg/payment-page?token=$payToken")
    }
}
```

### React Native 整合建議

```javascript
import { WebView } from 'react-native-webview';

function PaymentScreen({ payToken, onComplete }) {
  return (
    <WebView
      source={{ uri: `https://你的後端/ecpg/payment-page?token=${payToken}` }}
      onNavigationStateChange={(navState) => {
        if (navState.url.includes('/payment/complete')) {
          onComplete(navState.url);
        }
      }}
      javaScriptEnabled={true}
      domStorageEnabled={true}
    />
  );
}
```

### App 環境注意事項

| 項目 | 說明 |
|------|------|
| WebView User-Agent | 建議設定自訂 User-Agent，避免被當作爬蟲攔截 |
| Deep Link 回呼 | iOS 使用 Universal Link、Android 使用 App Links 處理付款完成回呼 |
| 外部瀏覽器 vs WebView | WebView 嵌入體驗好但需處理回呼；外部瀏覽器相容性高但體驗較差 |
| 3D Secure | 3D 驗證會在 WebView 中開啟，確保 WebView 支援 JavaScript 和 DOM Storage |
| Cookie 設定 | iOS 需允許 third-party cookies（`WKWebViewConfiguration.websiteDataStore`） |

詳細 App SDK 規格見：`references/Payment/站內付2.0API技術文件App.md`（39 個 URL）

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
$order = findOrder($decryptedData['MerchantTradeNo']);
if ((int)$decryptedData['TradeAmt'] !== $order->amount) {
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

## 相關文件

- Web API 規格：`references/Payment/站內付2.0API技術文件Web.md`（34 個 URL）
- App API 規格：`references/Payment/站內付2.0API技術文件App.md`（39 個 URL）
- AES 加解密：[guides/14-aes-encryption.md](./14-aes-encryption.md)
- 除錯指南：[guides/15-troubleshooting.md](./15-troubleshooting.md)
- 上線檢查：[guides/16-go-live-checklist.md](./16-go-live-checklist.md)

---

## Apple Pay 整合前置準備

> ⚠️ **Apple Pay 按鈕若完全不顯示，幾乎一定是以下三個步驟尚未完成，而不是程式碼問題。**  
> 請依序完成下列步驟後，再測試前端 JS SDK。

### 步驟一：在 Apple Developer 建立 Merchant ID

1. 登入 [Apple Developer](https://developer.apple.com/account/)
2. 前往 **Certificates, Identifiers & Profiles → Identifiers → Merchant IDs**
3. 建立新的 Merchant ID（格式：`merchant.com.yourdomain.ecpay`）

### 步驟二：部署域名驗證檔（Domain Verification）

Apple Pay 要求你在網站根目錄放置域名驗證檔：

| 項目 | 說明 |
|------|------|
| 檔案路徑 | `/.well-known/apple-developer-merchantid-domain-association` |
| 檔案內容 | 從 Apple Developer 下載的原始檔（**不要加附檔名**） |
| Content-Type | 無特殊要求（plain text 即可） |
| HTTPS 必要 | ✅ 必須透過 HTTPS 可存取（HTTP 不行） |
| 驗證網址 | `https://你的網域/.well-known/apple-developer-merchantid-domain-association` |

**常見問題：**
- ❌ 放在 `/apple-developer-merchantid-domain-association`（少了 `.well-known/`）
- ❌ 伺服器未設定 `.well-known/` 目錄的靜態路由
- ❌ 透過 HTTP（非 HTTPS）存取

```nginx
# Nginx 設定範例（確保可存取 .well-known/）
location /.well-known/ {
    root /var/www/html;
    allow all;
}
```

### 步驟三：上傳憑證到綠界後台

1. 登入**綠界商店後台**
2. 前往 **串接管理 → 站內付 2.0 設定 → Apple Pay**
3. 上傳 Merchant Identity Certificate（從 Apple Developer 下載）
4. 填入你的 Merchant ID
5. 填入要啟用 Apple Pay 的**已驗證域名**

> 📌 憑證有效期限通常為 25 個月，請設定提醒定期更新。

### 驗證 Apple Pay 是否正常

- 必須在**實際 Apple 裝置**（iPhone/Mac）上的 Safari 測試
- Chrome、Firefox 不支援 Apple Pay
- 使用 `ApplePaySession.canMakePayments()` 回傳 `true` 才代表裝置支援
- JS SDK 中設定 `ChoosePaymentList` 包含 `"7"` 後，Apple Pay 按鈕才會出現

---

## 正式環境實作注意事項

> 以下 3 個主題是測試環境完全正常、但正式環境上線後才會遇到的問題。**建議在切換正式環境前先閱讀。**

### 1. Token 刷新策略（10 分鐘過期）

**測試時不明顯，正式環境必會遇到**：消費者填寫信用卡資訊超過 10 分鐘（例如分心、離開頁面再回來），Token 過期導致 `getPayToken` 靜默失敗或 CreatePayment 回傳 `RtnCode≠1`。

**推薦實作模式**：

```python
import time

def get_token_with_expiry(trade_no: str) -> dict:
    """產生 Token 並記錄建立時間"""
    token_data = post_to_ecpay(f'{ECPG_URL}/Merchant/GetTokenbyTrade', {...})
    return {
        'token': token_data['Token'],
        'created_at': time.time(),
        'trade_no': trade_no
    }

def is_token_valid(token_record: dict, buffer_seconds: int = 60) -> bool:
    """Token 剩餘有效期 > buffer_seconds（預設保留 1 分鐘緩衝）"""
    return (time.time() - token_record['created_at']) < (600 - buffer_seconds)
```

```typescript
// Node.js：前端檢測 JS SDK 回傳的錯誤，觸發重新取號
// ⚠️ 此為概念範例，實際 callback 參數請依官方 JS SDK 文件為準
window.ECPay.createPayment({
  token: currentToken,
  onError: (err) => {
    if (err.code === 'TOKEN_EXPIRED' || err.message?.includes('Token')) {
      // 重新呼叫後端 /api/get-token 取得新 Token + 新 MerchantTradeNo
      fetch('/api/get-token').then(r => r.json()).then(({ token, tradeNo }) => {
        updateToken(token, tradeNo);  // 更新前端持有的 Token
      });
    }
  }
});
```

> 💡 **最佳 UX 建議**：在前端倒計時顯示 Token 剩餘時間（例如 "付款表單將在 8:32 後過期，請儘快完成填寫"），過期前 30 秒自動靜默刷新 Token。

### 2. ReturnURL 冪等性（防止重複出貨）

**問題**：ECPay 在你的 ReturnURL 回傳 `1|OK` 前最多重試 4 次（跨數小時）。若你的 ReturnURL handler 未做冪等性保護，同一筆訂單可能被多次觸發「出貨/發點數/更新餘額」的業務邏輯。

**保護模式**（Python 示例）：

```python
# 使用資料庫唯一鍵防止重複處理（以 MerchantTradeNo 為冪等鍵）
@app.route('/ecpay/notify', methods=['POST'])
def ecpay_notify():
    body = request.get_json(force=True)
    # ⚠️ AES-JSON 雙層驗證：先查 TransCode，再解密 Data
    if not body or int(body.get('TransCode', 0)) != 1:
        return '1|OK', 200, {'Content-Type': 'text/plain'}
    data = aes_decrypt(body['Data'])
    trade_no = data.get('MerchantTradeNo', '')
    rtn_code = int(data.get('RtnCode', 0))

    # ① 冪等性檢查：同一訂單已處理過，直接回傳 1|OK
    if db.order_already_processed(trade_no):
        return '1|OK', 200, {'Content-Type': 'text/plain'}

    # ② 業務邏輯（只執行一次）
    if rtn_code == 1:
        db.mark_order_paid(trade_no)   # 原子操作，使用資料庫唯一約束
        # fulfillment_service.ship(trade_no)  ← 出貨/發點數等

    return '1|OK', 200, {'Content-Type': 'text/plain'}
```

> ⚠️ **重要**：`db.mark_order_paid` 必須使用資料庫層的唯一約束（不可使用應用層的 if-else 判斷），因為高並發下多次請求可能同時通過應用層判斷。

### 3. TransCode≠1 錯誤降級

**問題**：在正式環境偶爾發生 AES 加解密失敗（例如伺服器時鐘偏差、負載高峰時的超時），此時 `TransCode≠1`，若直接拋出例外會導致整個付款流程中斷且消費者無法重試。

```python
def post_to_ecpay_safe(url: str, data: dict, max_retries: int = 2) -> dict | None:
    """帶重試的安全呼叫，TransCode≠1 時重試，失敗後回傳 None 讓上層降級處理"""
    for attempt in range(max_retries + 1):
        try:
            outer = requests.post(url, json={
                'MerchantID': MERCHANT_ID,
                'RqHeader': {'Timestamp': int(time.time())},  # 每次都重新取時間戳
                'Data': aes_encrypt(data)
            }, timeout=10).json()

            if outer.get('TransCode') == 1:
                return aes_decrypt(outer['Data'])
        except Exception as e:
            print(f'[ECPay] 嘗試 {attempt+1} 失敗: {e}')
        if attempt < max_retries:
            time.sleep(1)   # 重試前等待 1 秒

    return None   # 所有重試失敗 → 顯示「系統繁忙，請稍後再試」

# 使用方式
result = post_to_ecpay_safe(f'{ECPG_URL}/Merchant/GetTokenbyTrade', {...})
if result is None:
    return '系統暫時無法處理，請稍後再試', 503
```

---

## 正式環境切換清單

> 在測試環境整合完成後，切換至正式環境時**必須同步修改以下所有項目**。遺漏任何一項會導致正式環境請求失敗。

### 端點與 URL

| 項目 | 測試環境 | 正式環境 |
|------|---------|---------|
| GetToken / 付款端點 | `ecpg-stage.ecpay.com.tw` | `ecpg.ecpay.com.tw` |
| 查詢 / 退款端點 | `ecpayment-stage.ecpay.com.tw` | `ecpayment.ecpay.com.tw` |
| JS SDK URL | `ecpg-stage.ecpay.com.tw/scripts/sdk/ecpay.payment.js` | `ecpg.ecpay.com.tw/scripts/sdk/ecpay.payment.js` |

### 憑證與帳號

| 項目 | 說明 |
|------|------|
| MerchantID | 從測試帳號改為正式帳號 |
| HashKey | 從測試值改為正式環境的 HashKey |
| HashIV | 從測試值改為正式環境的 HashIV |

> 🔐 **正式 HashKey / HashIV 請從綠界商店後台**取得，不要寫死在程式碼中，請存放至環境變數或 Secret Manager。

### Callback URL

| 項目 | 說明 |
|------|------|
| ReturnURL | 確認指向**正式伺服器**的可公開存取 HTTPS URL |
| OrderResultURL | 確認指向**正式伺服器**（若有使用） |
| ClientBackURL | 確認指向**正式前端頁面** |

### Apple Pay（若有使用）

| 項目 | 說明 |
|------|------|
| 域名驗證檔 | 確認在**正式域名**部署 |
| 後台設定 | 確認在綠界**正式後台**填入正式域名 |

### 切換後驗證步驟

1. ✅ 用正式帳號執行一筆小額信用卡交易（如 1 元）
2. ✅ 確認 ReturnURL 收到正式環境的 Callback
3. ✅ 在正式後台確認訂單狀態
4. ✅ 若有 ATM/CVS，用測試模擬付款確認 Callback 時序
5. ✅ 若有 Apple Pay，在真實 Apple 裝置確認按鈕顯示
