> 對應 ECPay API 版本 | 基於 PHP SDK ecpay/sdk | 最後更新：2026-03

> 📖 本文件為 [guides/02 站內付 2.0 完整指南](./02-payment-ecpg.md) 的子指南 — 首次串接快速路徑

> ⚠️ **站內付 2.0 使用兩個不同 Domain — 打錯立得 HTTP 404**
>
> | API 類別 | 測試 Domain | 正式 Domain |
> |---------|------------|------------|
> | Token 取得 API（GetTokenbyTrade、CreatePayment） | `ecpg-stage.ecpay.com.tw` | `ecpg.ecpay.com.tw` |
> | 查詢 / 請款 / 退款 API | `ecpayment-stage.ecpay.com.tw` | `ecpayment.ecpay.com.tw` |
>
> 先確認 Domain 再開始撰寫程式碼。完整說明見 [guides/02 §Domain 警告](./02-payment-ecpg.md)。

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

> ⚠️ **3D Secure 強制流程（2025/8 起必讀）**：CreatePayment 回應中若含非空 `ThreeDInfo.ThreeDURL`，前端**必須**立即執行 `window.location.href = threeDUrl`（不可用 router.push / fetch）。2025/8 後幾乎所有信用卡交易都會進入 3D 驗證流程。**省略 ThreeDURL 判斷是站內付 2.0 最常見的致命錯誤，會導致交易永遠逾時失敗。** 詳見下方步驟 4。

| 項目 | 測試環境值 | 確認 |
|------|-----------|:----:|
| MerchantID | `3002607` | □ |
| HashKey | `pwFHCqoQZGmho4w6`（16 bytes） | □ |
| HashIV | `EkRm7iFT261dpevs`（16 bytes） | □ |
| **AES-128-CBC 加解密已實作**（站內付 2.0 核心依賴）| 見 [guides/14](./14-aes-encryption.md) | □ |
| 後端 ReturnURL 端點可接收 HTTP POST | — | □ |
| 後端 OrderResultURL 端點可接收 HTTP POST | — | □ |
| 前端頁面可引入外部 JavaScript | — | □ |

> **測試信用卡號**（官方規格 8981.md）：
>
> | 類型 | 卡號 | 安全碼 | 說明 |
> |------|------|--------|------|
> | 一般信用卡 | `4311-9522-2222-2222` | 任意 3 碼 | 通用測試卡號 |
> | 永豐30期 | `4938-1777-7777-7777` | 任意 3 碼 | ChoosePaymentList=8 時使用 |
> | 海外信用卡 | `4000-2011-1111-1111` | 任意 3 碼 | 測試環境未提供海外卡服務 |
> | 美國運通（國內）| `3403-532780-80900` | — | **限閘道商**（MerchantID=3085779）使用 |
> | 美國運通（國外）| `3712-222222-22222` | — | **限閘道商**（MerchantID=3085779）使用 |
>
> 有效期：輸入大於當前月年的值（如 `12/28`），3D 驗證碼（測試環境固定）：`1234`

> **平台商測試資料(僅限平台商模式使用)**(官方規格 8981.md):
>
> ⚠️ **一般商店請勿使用此區資料** — 一般特店應填 `PlatformID=''`(空字串)並使用前述「MerchantID=3002607」的一般商戶測試帳號。以下 `PlatformID=3003008` 僅適用於平台商模式(多商戶代收代付場景),誤用會導致 `TransCode ≠ 1` 或業務層錯誤。詳見 [guides/02 §平台商模式](./02-payment-ecpg.md)。
>
> | 項目 | 值 | 用途 |
> |------|-----|------|
> | PlatformID | `3003008` | **僅平台商專用** |
> | 後台帳號 / 密碼 | `StageTestV3P` / `test1234` | 平台商後台登入 |
> | 統一編號 | `10608171` | 平台商統編 |
> | HashKey | `FCnGLNS7P3xQ2q3E` | **與一般商店 HashKey 完全不同** |
> | HashIV | `awL5GRWRhyaybq13` | **與一般商店 HashIV 完全不同** |

> **閘道商測試資料（美國運通/國旅卡）**（官方規格 8981.md）：
>
> | 項目 | 值 |
> |------|-----|
> | MerchantID | `3085779` |
> | 後台帳號 / 密碼 | `gatewaytest02` / `test1234` |
> | HashKey | `y6869NBszTuvhSRx` |
> | HashIV | `BMm7FmX91dE8rpdw` |

> ⚠️ **非 PHP 語言**：AES-128-CBC 加解密是站內付 2.0 所有請求的基礎。**開始撰寫任何業務代碼前，請先讀 [guides/14-aes-encryption.md](./14-aes-encryption.md) 並確認你的語言的 AES 實作正確**（含 URL Encode 前置步驟）。省略這步是導致非 PHP 串接失敗的首要原因。

> **ReturnURL / OrderResultURL 尚未準備好？** 先用 [RequestBin](https://requestbin.com) 或 `ngrok http 3000` 建立暫時端點，確認付款流程後再串接正式 callback 邏輯。無公開 URL 的替代方案見本節末尾。

### GetTokenbyTrade Data 必填欄位速查

> 📋 根據官方規格（`references/Payment/站內付2.0API技術文件Web.md` §取得廠商驗證碼/付款）整理。
> 程式碼生成前請對照此表確認每個欄位是否應填入。

| 欄位路徑 | 類型 | 必填？ | 說明 / 常見陷阱 |
|---------|------|:------:|----------------|
| `MerchantID`（Data 內） | String(10) | ✅ 必填 | 外層也有一個，**兩處都要填**（最常漏的坑） |
| `RememberCard` | Int | ✅ 必填 | `0`=不綁卡（測試時用這個）`1`=啟用綁卡（= 1 時 `ConsumerInfo.MerchantMemberID` 也必填） |
| `PaymentUIType` | Int | ✅ 必填 | `0`=定期定額 `2`=付款選擇清單頁（一般使用）`5`=Apple Pay 延遲付款（CreatePayment 需帶 `Total`）。⚠️ **2026-03 官方規格驗證**（web_fetch 9040.md）：僅支援 0/2/5，若過去使用 `1`=信用卡一次付清，請改用 `PaymentUIType=2` + `ChoosePaymentList='1'` |
| `ChoosePaymentList` | String(30) | ✅ PaymentUIType=2 時必填 | **字串型別，不是整數**：`'0'`=全部 `'1'`=信用卡 `'2'`=分期 `'3'`=ATM `'4'`=CVS `'5'`=超商條碼 `'6'`=銀聯卡 `'7'`=Apple Pay `'8'`=永豐30期；多選：`'1,3,4'` |
| `OrderInfo.MerchantTradeDate` | String(20) | ✅ 必填 | 格式：`'yyyy/MM/dd HH:mm:ss'`（GMT+8，非 Unix timestamp） |
| `OrderInfo.MerchantTradeNo` | String(20) | ✅ 必填 | 每次唯一；英數字 a-zA-Z0-9，最長 20 字元；**GetToken 與 CreatePayment 必須完全相同** |
| `OrderInfo.TotalAmount` | Int | ✅ 必填 | 整數（不含小數點），新台幣 |
| `OrderInfo.ReturnURL` | String(200) | ✅ 必填 | 公開 HTTPS URL（localhost/127.0.0.1 無效） |
| `OrderInfo.TradeDesc` | String(200) | ✅ 必填 | 交易描述 |
| `OrderInfo.ItemName` | String(400) | ✅ 必填 | 商品名稱（多件用 `#` 分隔）。⚠️ **官方建議不超過 200 字元**，超長內容會被截斷，若含多位元組字元（中文）可能導致 CheckMacValue 不符 |
| `CardInfo.OrderResultURL` | String(200) | ✅ PaymentUIType=0,1 或 ChoosePaymentList 含 0,1,2 時必填 | **ATM / CVS / Barcode 不需要**；**⚠️ 銀聯卡(6)需用 `UnionPayInfo.OrderResultURL`，不是此欄位** |
| `CardInfo.Redeem` | String(1) | — 選填 | 是否使用紅利折抵。⚠️ **實測確認**：傳入字串 `"N"`/`"Y"` 會導致 API 回傳 `5100011 The parameter [Redeem] Incorrect format`。PHP SDK 範例使用整數 `0`（PHP 弱型別可運作）。**非 PHP 語言建議直接省略此選填欄位**（使用預設值不折抵），避免格式問題。若必須指定，請先以實際 API 測試確認可接受的格式 |
| `ATMInfo.ExpireDate` | Int | ✅ ChoosePaymentList=3 時必填 | 繳費有效天數（1~60，預設 3） |
| `ATMInfo.ATMBankCode` | String(10) | — 選填 | 指定繳費銀行代碼，見[銀行代碼表](https://developers.ecpay.com.tw/9113.md)。不指定時系統預設特店申請的 ATM 繳費銀行 |
| `CVSInfo.StoreExpireDate` | Int | ✅ ChoosePaymentList=4 時必填 | 分鐘數（預設 10080=7天，最長 43200=30天） |
| `CVSInfo.CVSCode` | String(10) | — 選填 | 指定超商：`CVS`=不指定（預設）、`OK`=OK超商、`FAMILY`=全家、`HILIFE`=萊爾富、`IBON`=7-11 |
| `CVSInfo.Desc_1` | String(20) | — 選填 | 交易描述 1，FAMILY/IBON 時顯示於繳費平台螢幕 |
| `CVSInfo.Desc_2` | String(20) | — 選填 | 交易描述 2，FAMILY/IBON 時顯示於繳費平台螢幕 |
| `CVSInfo.Desc_3` | String(20) | — 選填 | 交易描述 3，FAMILY/IBON 時顯示於繳費平台螢幕 |
| `CVSInfo.Desc_4` | String(20) | — 選填 | 交易描述 4，FAMILY/IBON 時顯示於繳費平台螢幕 |
| `BarcodeInfo.StoreExpireDate` | Int | ✅ ChoosePaymentList=5 時必填 | 天數（預設 7，最長 30） |
| `ConsumerInfo`（整體 Object） | Object | ✅ **必填** | **整個 Object 不可省略**，即使只填最少欄位也必須傳入 |
| `ConsumerInfo.Email` | String(100) | ✅ Email / Phone **擇一**必填 | 格式需符合 email 正規表達式；可與 Phone 同時填 |
| `ConsumerInfo.Phone` | String(60) | ✅ Email / Phone **擇一**必填 | 台灣格式如 `'0912345678'`；海外需加國碼如 `'886912345678'` |
| `ConsumerInfo.MerchantMemberID` | String(60) | ✅ RememberCard=1 時必填 | 你系統的會員 ID；RememberCard=0 時選填 |
| `ConsumerInfo.Name` | String(50) | — 選填 | 消費者姓名 |
| `ConsumerInfo.CountryCode` | String(3) | — 選填 | ISO3166 國別碼（台灣：`'158'`） |
| `ConsumerInfo.Address` | String(50) | — 選填 | 消費者地址 |
| `CustomField` | String(200) | — 選填 | 自訂欄位，提供特店客制化使用。請勿傳入超過 200 字元的內容 |
| `PlatformID` | String(10) | — 選填 | 平台商才需要；一般特店留空或省略 |

> ⚠️ **ConsumerInfo 是整體必填（官方規格明確標示 必填）**：若完全省略 `ConsumerInfo` 物件，API 會回傳 `TransCode=0`（加密驗證失敗）或業務層錯誤。最少需傳入 `Email` 或 `Phone` 其中一個。

> 💡 **消費者自費分期**（官方規格 9040.md 2026-03）：當 `ChoosePaymentList` 包含 `0,1,2` 且 `CreditInstallment` 為 `3` 時，消費金額達 **1,000 元（含）以上**即可使用「消費者自費分期」。此功能為信用卡一次付清及分期付款的附加服務，無法單獨使用。消費者自費分期**不支援**信用卡紅利、永豐30期、定期定額及銀聯卡。當選擇信用卡分期、自費分期付款方式，輸入卡號判斷為簽帳金融卡時，會阻擋限制交易。若需關閉此功能，特約會員請洽所屬業務人員申請，一般前台會員無法申請關閉。詳見 [消費者自費分期說明](https://support.ecpay.com.tw/5018.md)。

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

### ⚡ API 端點速查（測試環境）

> 這是最常出錯的地方：`ecpg` vs `ecpayment` — 打錯立得 HTTP 404。

| 步驟 | API | Domain（測試） | Domain（正式） |
|:----:|-----|:------------:|:-------------:|
| 1 | GetTokenbyTrade | `ecpg-stage.ecpay.com.tw` | `ecpg.ecpay.com.tw` |
| 2 | JS SDK Script | `ecpg.ecpay.com.tw/Scripts/sdk-1.0.0.js`（⚠️ **測試/正式都用正式 domain**，透過 `initialize('Stage')` 切換） | `ecpg.ecpay.com.tw/Scripts/sdk-1.0.0.js` |
| 4 | CreatePayment | `ecpg-stage.ecpay.com.tw` | `ecpg.ecpay.com.tw` |
| 查詢/請退款 | QueryTrade / DoAction | `ecpayment-stage.ecpay.com.tw` | `ecpayment.ecpay.com.tw` |

> ⚠️ **GetTokenbyTrade、CreatePayment → ecpg domain**；**QueryTrade、DoAction → ecpayment domain**。兩者絕對不可混用。

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
            "CardInfo": {"OrderResultURL": "https://example.com/result"},  # Redeem 選填，建議省略以避免格式問題
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
                CardInfo: { OrderResultURL: 'https://example.com/result' },  // Redeem 選填，建議省略
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
        'CardInfo'     => ['Redeem' => 0, 'OrderResultURL' => 'https://你的網站/ecpay/result'],  // ⚠️ Redeem 整數 0 僅適用 PHP 弱型別;JS/TS/Python/Go 等強型別語言務必傳整數 0(非字串 "0"/"N"),或直接**省略此選填欄位**(預設不折抵)。字串 "N"/"Y" 會觸發 `5100011 The parameter [Redeem] Incorrect format`
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

> ✅ **步驟 1 成功標誌**
>
> | 預期觀察 | 說明 |
> |---------|------|
> | `TransCode === 1` | 外層 AES 傳輸層驗證通過 |
> | `RtnCode === 1`（整數） | 業務層成功（AES-JSON 協議，**整數**，不是字串 `'1'`） |
> | `Token` 為非空字串 | `Data` 解密後 `TokenModel.Token` 有值（長度通常 > 10 字元） |
>
> HTTP 回應（外層）：`{"TransCode": 1, "TransMsg": "Success", "Data": "<Base64字串>"}`
>
> 解密 `Data` 後（內層）：`{"RtnCode": 1, "RtnMsg": "成功", "Token": "ecpay123..."}`
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
            CardInfo: { OrderResultURL: 'https://你的網站/ecpay/result' },  // Redeem 選填，建議省略
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
<!-- ⚠️ 三個依賴缺一不可：jQuery → node-forge → ECPay SDK，順序不可調換 -->
<!-- ⚠️ JS SDK 一律從正式 domain 載入，不要用 ecpg-stage（stage 版是不同檔案，行為異常） -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/node-forge@0.7.0/dist/forge.min.js"></script>
<!--渲染付款界面UI，請勿更動id-->
<div id="ECPayPayment"></div>
<script src="https://ecpg.ecpay.com.tw/Scripts/sdk-1.0.0.js"></script>

<script>
  const _token = '{{ 步驟1後端傳入的Token }}';  // 純字串

  // ⚠️ initialize 第一個參數為字串 'Stage'（測試）或 'Prod'（正式），非整數
  // ⚠️ createPayment() 必須在 initialize() callback 內（官方 WebJS.html 寫法）
  //    寫在外面會造成競態條件：SDK 未初始化完就嘗試渲染 → 永遠轉圈
  ECPay.initialize('Stage', 1, function(errMsg) {
    if (errMsg != null) { console.error('SDK 初始化失敗:', errMsg); return; }
    // SDK 自動渲染至 <div id="ECPayPayment">（固定 ID，不可更改）
    ECPay.createPayment(_token, 'zh-TW', function(errMsg) {
      if (errMsg != null) { console.error('建立付款 UI 失敗:', errMsg); return; }
    }, 'V2');
  });

  // 消費者填卡完成後，取得 PayToken
  function getPayTokenAndSubmit() {
    ECPay.getPayToken(function(paymentInfo, errMsg) {
      if (errMsg != null) { console.error('取 PayToken 失敗:', errMsg); return; }
      console.log('PayToken 型別:', typeof paymentInfo.PayToken);  // 應為 "string"
      submitToBackend({ payToken: paymentInfo.PayToken, merchantTradeNo: '步驟1使用的訂單編號' });
    });
  }
</script>
```

> ⚠️ **若 `#ECPayPayment` 的父容器用 CSS 隱藏再顯示**，JS 顯示時必須明確設定
> `element.style.display = 'block'`，**不可**用 `element.style.display = ''`（清除
> inline style 不會覆蓋 CSS rule，容器仍為隱藏，SDK 無聲略過渲染）。
> 建議用雙層 `requestAnimationFrame` 包住 `ECPay.initialize()` 確保 repaint 完成後再執行。

> **⚠️ 步驟 2 失敗排查**
>
> | 症狀 | 最可能原因 | 解法 |
> |------|-----------|------|
> | 頁面無任何輸入框 | Token 無效或已逾時（預設 10 分鐘） | 回步驟 1 確認 Token 非空；縮短測試流程時間 |
> | Console CORS 錯誤 | 測試/正式環境 SDK URL 與 Token 不一致 | 確認 SDK URL 與 GetToken 端點環境完全一致 |
> | Console CSP 錯誤 | Content-Security-Policy 阻擋 | CSP header 加入 `https://ecpg-stage.ecpay.com.tw`（script-src、frame-src、connect-src） |
> | callback 未觸發 | 消費者尚未填卡並點擊付款 | 確認 `<div id="ECPayPayment">` 存在且可見 |
> | `#ECPayPayment` 存在但完全空白，Console 無錯誤 | 父容器被 CSS `display:none` 隱藏，JS 只清除了 inline style（`= ''`），CSS rule 仍生效，容器高度 = 0，SDK 略過渲染 | 改用 `element.style.display = 'block'` 明確覆蓋；並用雙層 `requestAnimationFrame` 延後呼叫 `ECPay.initialize()` |
> | 點擊按鈕後才顯示付款區塊，`#ECPayPayment` 空白無表單（Console 無錯誤） | 在同一 JS tick 內切換顯示後立即呼叫 SDK，瀏覽器 repaint 尚未完成，容器尺寸仍為 0 | 用雙層 rAF 延後：`requestAnimationFrame(() => requestAnimationFrame(() => ECPay.initialize(...)))` |

> ✅ **步驟 2 成功的視覺確認**
>
> 頁面出現 ECPay 付款表單，包含信用卡號、有效期（MM/YY）、安全碼三個輸入欄位。
>
> ⚠️ 若在步驟 1 取得 Token 後超過 **10 分鐘**才到步驟 2，表單會消失（Token 過期）。此時需回步驟 1，用**新的 MerchantTradeNo** 重新取得 Token。

---

#### 步驟 3：前端取得 PayToken

**目標**：消費者填入測試卡號後，步驟 2 的 `callback` 回傳 `paymentInfo.PayToken`（非空字串）。

**測試卡號**：卡號 `4311-9522-2222-2222`，有效期大於當前月年（例如 `12/28`），安全碼 `222`，3D 驗證碼（測試環境固定）`1234`

> 在上方步驟 2 的 `getPayToken` callback 中，第一個參數 `paymentInfo` 是**物件**，`paymentInfo.PayToken` 才是 PayToken 字串。
>
> 將 `paymentInfo.PayToken` 連同步驟 1 使用的同一個 `merchantTradeNo` 一起送往後端，供步驟 4 使用。

> **⚠️ 步驟 3 失敗排查**
>
> | 症狀 | 最可能原因 | 解法 |
> |------|-----------|------|
> | `errMsg` 非 null | 卡號/有效期/安全碼格式錯誤 | 確認卡號、有效期（MM/YY）、安全碼 3 位數均已填入 |
> | 後端收到 `[object Object]` | 把 callback 的 `paymentInfo` 物件整個傳過去 | 只傳 `paymentInfo.PayToken` 字串，確認 `typeof paymentInfo.PayToken === 'string'` |
> | `paymentInfo` 為 null/undefined | SDK 邊界狀態：errMsg 為空字串（falsy）但 paymentInfo 無效 | errMsg 檢查必須用 `errMsg != null`（非 `if (errMsg)`），並額外檢查 `paymentInfo?.PayToken` |

> ✅ **步驟 3 成功時的預期輸出**
>
> `paymentInfo.PayToken` 為非空字串（`typeof paymentInfo.PayToken === 'string'` 為 `true`）。
>
> ⚠️ PayToken 是**一次性**的——步驟 4（CreatePayment）呼叫後即消耗，不可重複使用。
>
> ⚠️ **PayToken 格式未定義**：ECPay 官方未規範 PayToken 的字元集或長度上限。實際值可能包含英數字以外的字元（如 `.`、`-`、`:`、`%` 等，或為 JWT 格式），長度可能超過 200 字元。後端**不應對 PayToken 做正規表達式格式驗證**，僅需確認為非空字串。PayToken 是 SDK 內部產生的值，不是使用者輸入。

---

#### 步驟 4：後端建立交易並判斷 ThreeDURL

**目標**：呼叫 CreatePayment，回應含 `ThreeDURL`（需導向 3D 驗證）或 `RtnCode=1`（直接成功）。

**端點**：`POST https://ecpg-stage.ecpay.com.tw/Merchant/CreatePayment`（仍是 `ecpg-stage`）

```php
$data = [
    'MerchantID'      => '3002607',
    'PayToken'        => $_POST['payToken'],           // 步驟 3 的 PayToken
    'MerchantTradeNo' => $_POST['merchantTradeNo'],    // 步驟 1 相同的訂單編號
];

// ⚠️ Apple Pay 延遲付款（PaymentUIType=5）時必填：最終授權金額（Int，不可為 0）
if (isset($_POST['total']) && $_POST['total'] > 0) {
    $data['Total'] = (int)$_POST['total'];
}

$response = $postService->post([
    'MerchantID' => '3002607',
    'RqHeader'   => ['Timestamp' => time()],
    'Data'       => $data,
], 'https://ecpg-stage.ecpay.com.tw/Merchant/CreatePayment');

$data = $response['Data'];  // PHP SDK 已自動解密

// ⚠️ **重要：官方規格更新提醒（SNAPSHOT 2026-03）**
// 基於 web_fetch 9053.md 確認：CreatePayment 回應為巢狀結構
// - 3D 驗證：ThreeDInfo.ThreeDURL
// - 訂單資訊：OrderInfo.TradeNo
// - 銀聯卡驗證：UnionPayInfo.UnionPayURL
// ⚠️ 必須先判斷 ThreeDURL — 2025/8 起幾乎所有信用卡交易都會有
$threeDUrl = $data['ThreeDInfo']['ThreeDURL'] ?? '';
$unionPayUrl = $data['UnionPayInfo']['UnionPayURL'] ?? '';

if ($threeDUrl !== '') {
    // 將 ThreeDURL 回傳給前端，前端執行跳轉
    echo json_encode(['threeDUrl' => $threeDUrl]);
} elseif ($unionPayUrl !== '') {
    // 銀聯卡驗證跳轉
    echo json_encode(['unionPayUrl' => $unionPayUrl]);
} elseif (($data['RtnCode'] ?? null) === 1) {
    // 不需 3D 驗證，交易直接成功
    echo json_encode([
        'success' => true, 
        'tradeNo' => $data['OrderInfo']['TradeNo'] ?? '',
        'tradeAmt' => $data['OrderInfo']['TradeAmt'] ?? '',
        'chargeFee' => $data['OrderInfo']['ChargeFee'] ?? 0,
        'processFee' => $data['OrderInfo']['ProcessFee'] ?? 0
    ]);
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
> | `TransCode` ≠ 1 | PayToken 過期或無效 | 確認步驟 3→4 間隔不超過 10 分鐘；確認傳的是 `paymentInfo.PayToken` 字串（非物件） |
> | `RtnCode` ≠ 1 且 `ThreeDURL` 空 | 卡片授權失敗 | 讀 `RtnMsg`；測試時確認使用測試卡號 `4311-9522-2222-2222` |
> | 交易建立後無任何反應、最終逾時 | **未處理 ThreeDURL**（最常見錯誤） | 加入 ThreeDURL 判斷並確認前端執行 `window.location.href` |

> ✅ **步驟 4 成功標誌**
>
> | 預期觀察 | 說明 |
> |---------|------|
> | `TransCode === 1` | AES 傳輸層通過 |
> | 情況 A：`ThreeDURL` 為非空字串 | 前端必須執行 `window.location.href = threeDUrl`，瀏覽器顯示 3D 驗證頁面（付款 UI 已呈現給消費者） |
> | 情況 B：`ThreeDURL` 為空 + `RtnCode === 1`（整數） | 交易直接成功，`OrderInfo.TradeNo` 有值（**AES-JSON 協議，RtnCode 為整數，不是字串 `'1'`**） |
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

    // ⚠️ 官方規格（9053.md）回應為巢狀結構：ThreeDInfo.ThreeDURL、OrderInfo.TradeNo 等
    // ⚠️ ThreeDURL 必須先判斷（2025/8 後幾乎必定進入此分支）
    const threeDUrl = data.ThreeDInfo?.ThreeDURL ?? '';
    if (threeDUrl !== '') {
        return res.status(200).json({ threeDUrl });
    }
    if (data.RtnCode === 1) {  // RtnCode 是整數（AES-JSON 解密後）
        return res.status(200).json({ success: true, tradeNo: data.OrderInfo?.TradeNo });
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
    // ⚠️ 官方規格（9058.md）回應為巢狀結構：訂單資訊在 OrderInfo 物件內
    // 更新訂單狀態：$data['OrderInfo']['MerchantTradeNo']、$data['OrderInfo']['TradeNo']
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
// ⚠️ 官方規格（15076.md）回應為巢狀結構：訂單資訊在 OrderInfo 物件內
// 顯示結果頁面（不需回應 1|OK）
echo $data['RtnCode'] === 1
    ? '付款成功，訂單：' . ($data['OrderInfo']['MerchantTradeNo'] ?? '')
    : '付款失敗：' . $data['RtnMsg'];
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
        // ⚠️ 官方規格（9058.md）回應為巢狀結構：訂單資訊在 OrderInfo 物件內
        await updateOrderStatus(data.OrderInfo?.MerchantTradeNo, 'paid');  // 更新訂單
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
    // ⚠️ 官方規格（15076.md）回應為巢狀結構：訂單資訊在 OrderInfo 物件內
    if (data.RtnCode === 1) {  // RtnCode 是整數（AES-JSON 解密後）
        res.send(`<h1>付款成功！訂單：${data.OrderInfo?.MerchantTradeNo}</h1>`);
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

> ✅ **步驟 5 成功標誌**
>
> **ReturnURL（S2S）**
>
> | 預期觀察 | 說明 |
> |---------|------|
> | `TransCode === 1` | AES 傳輸層通過 |
> | AES 解密 `Data` 成功 | 使用相同 HashKey/HashIV 解密，無 padding 錯誤 |
> | `RtnCode === 1`（整數） | 付款成功（**AES-JSON 協議，整數 `1`，不是字串 `'1'`**） |
> | `OrderInfo.MerchantTradeNo` 與步驟 1 一致 | 訂單歸屬驗證通過 |
> | 你的端點回應純文字 `1\|OK` + HTTP 200 | 綠界確認收到通知，停止重試 |
>
> **OrderResultURL（瀏覽器跳轉）**
>
> | 預期觀察 | 說明 |
> |---------|------|
> | `ResultData` 欄位存在 | Form POST 欄位，非 JSON body |
> | `JSON.parse(ResultData).TransCode === 1` | 外層 JSON 解析成功 |
> | AES 解密 `Data` 後 `RtnCode === 1`（整數） | 付款成功 |
> | 直接顯示結果頁面給消費者（不回應 `1\|OK`） | OrderResultURL 無需回應確認 |
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
        pass  # 更新訂單狀態（update_order(data['OrderInfo']['MerchantTradeNo'], 'paid')）
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
        return f"<h1>付款成功！訂單：{data.get('OrderInfo', {}).get('MerchantTradeNo', '')}</h1>"
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
        'PlatformID'      => '',         // 一般商店填空字串；平台商模式填平台商 ID
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
  <!-- 步驟 2：載入綠界 JS SDK — 三個依賴缺一不可 -->
  <!-- ⚠️ JS SDK 一律從正式 domain 載入，透過 initialize('Stage') 切換環境 -->
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/node-forge@0.7.0/dist/forge.min.js"></script>
  <script src="https://ecpg.ecpay.com.tw/Scripts/sdk-1.0.0.js"></script>
</head>
<body>
  <h1>站內付 2.0 — 完整範例</h1>
  <p>訂單：<span id="order-id"></span></p>
  <!--渲染付款界面UI，請勿更動id-->
  <div id="ECPayPayment"></div>
  <div id="status" style="margin-top:1em;color:#666;"></div>
  <button id="btn-pay" style="display:none;" onclick="getPayTokenAndSubmit()">確認付款</button>
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
      // 步驟 2：初始化 SDK + 渲染付款 UI（自動渲染至 #ECPayPayment）
      // ⚠️ initialize 第一個參數為字串 'Stage'/'Prod'，非整數
      // ⚠️ createPayment() 必須在 initialize() callback 內（官方寫法），否則競態條件導致轉圈
      ECPay.initialize('Stage', 1, function(errMsg) {
        if (errMsg != null) { setStatus('SDK 初始化失敗：' + errMsg); return; }
        ECPay.createPayment(token, 'zh-TW', function(errMsg) {
          if (errMsg != null) { setStatus('渲染失敗：' + errMsg); return; }
          document.getElementById('btn-pay').style.display = 'block';
        }, 'V2');
      });

      // 步驟 3：消費者填卡後，點擊「確認付款」取 PayToken
      window.getPayTokenAndSubmit = function() {
        ECPay.getPayToken(function(paymentInfo, errMsg) {
          if (errMsg != null) { setStatus('取 PayToken 失敗：' + errMsg); return; }
          setStatus('取得 PayToken，送出付款中…');
          // 步驟 4：後端呼叫 CreatePayment
          fetch('/ecpay/create_payment', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ payToken: paymentInfo.PayToken, merchantTradeNo }),
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
        });
      };
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
            'CardInfo': {'OrderResultURL': RESULT_URL},  # Redeem 選填，省略避免格式問題
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
        three_d_url = data.get('ThreeDInfo', {}).get('ThreeDURL', '').strip()
        if three_d_url:
            return jsonify({'threeDUrl': three_d_url})
        rtn_code = int(data.get('RtnCode', 0))
        if rtn_code == 1:
            return jsonify({'success': True, 'tradeNo': data.get('OrderInfo', {}).get('TradeNo')})
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
        trade_no = data['OrderInfo']['MerchantTradeNo']
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
        return f"<h1>✅ 付款成功！訂單：{data['OrderInfo']['MerchantTradeNo']}</h1>", 200
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
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/node-forge@0.7.0/dist/forge.min.js"></script>
<script src="https://ecpg.ecpay.com.tw/Scripts/sdk-1.0.0.js"></script>
</head><body>
<h1>站內付 2.0 — 完整範例（Node.js）</h1>
<p>訂單：<span id="order-id"></span></p>
<!--渲染付款界面UI，請勿更動id-->
<div id="ECPayPayment"></div>
<div id="status" style="margin-top:1em;color:#666;"></div>
<button id="btn-pay" style="display:none;" onclick="getPayTokenAndSubmit()">確認付款</button>
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
    // ⚠️ initialize 第一個參數為字串 'Stage'/'Prod'，非整數
    // ⚠️ createPayment() 必須在 initialize() callback 內（官方寫法）
    ECPay.initialize('Stage', 1, function(e) {
      if (e != null) { setStatus('SDK init 失敗：'+e); return; }
      ECPay.createPayment(token, 'zh-TW', function(e) {
        if (e != null) { setStatus('渲染失敗：'+e); return; }
        document.getElementById('btn-pay').style.display = 'block';
      }, 'V2');
    });
    window.getPayTokenAndSubmit = function() {
      ECPay.getPayToken(function(info, e) {
        if (e != null) { setStatus('PayToken 失敗：'+e); return; }
        setStatus('送出付款中…');
        fetch('/ecpay/create_payment', {
          method: 'POST', headers: {'Content-Type':'application/json'},
          body: JSON.stringify({ payToken: info.PayToken, merchantTradeNo }),
        }).then(r => r.json()).then(({ threeDUrl, error }) => {
          if (error) { setStatus('付款失敗：' + error); return; }
          if (threeDUrl) { window.location.href = threeDUrl; }
          else { setStatus('✅ 付款成功！'); }
        });
      });
    };
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
      CardInfo: { OrderResultURL: RESULT_URL },  // Redeem 選填，省略避免格式問題
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
    const threeDUrl = data.ThreeDInfo?.ThreeDURL?.trim();
    if (threeDUrl) return res.json({ threeDUrl });
    const rtnCode = Number(data.RtnCode);
    if (rtnCode === 1) return res.json({ success: true, tradeNo: data.OrderInfo?.TradeNo });
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
    console.log('[ReturnURL] ✅ 付款成功 訂單=', data.OrderInfo?.MerchantTradeNo);
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
    return res.send(`<h1>✅ 付款成功！訂單：${data.OrderInfo?.MerchantTradeNo}</h1>`);
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
    # 成功回應（RtnCode=1）包含（⚠️ 官方規格為巢狀結構）：
    # pay_data['ATMInfo']['BankCode']      = '812'        ← 銀行代碼
    # pay_data['ATMInfo']['vAccount']      = '9103522850' ← 虛擬帳號
    # pay_data['ATMInfo']['ExpireDate']    = '2026/03/20' ← 繳費期限
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
    ''', bank_code=pay_data['ATMInfo']['BankCode'], vaccount=pay_data['ATMInfo']['vAccount'],
         expire=pay_data['ATMInfo']['ExpireDate'], trade_no=trade_no)

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
    # pay_data['CVSInfo']['PaymentNo']   = 'LLL22251222'    ← 超商繳費代碼
    # pay_data['CVSInfo']['ExpireDate']  = '2026/03/20 23:59:59'

    return render_template_string('''
        <h2>請到超商繳費</h2>
        <table border="1" cellpadding="8">
          <tr><th>超商代碼</th><td><b>{{ payment_no }}</b></td></tr>
          <tr><th>繳費金額</th><td>NT$ 100</td></tr>
          <tr><th>繳費期限</th><td>{{ expire }}</td></tr>
          <tr><th>訂單號碼</th><td>{{ trade_no }}</td></tr>
        </table>
        <p><small>⚠️ 繳費完成後由綠界非同步通知 ReturnURL（可能需數分鐘至數小時）</small></p>
    ''', payment_no=pay_data['CVSInfo']['PaymentNo'], expire=pay_data['CVSInfo']['ExpireDate'], trade_no=trade_no)

# ─────────────────── ReturnURL：接收綠界非同步繳費通知 ───────────────────
@app.route('/ecpay/notify', methods=['POST'])
def ecpay_notify():
    body   = request.get_json(force=True)
    # ⚠️ AES-JSON 雙層驗證：先查 TransCode（傳輸層），再解密 Data（業務層）
    if not body or int(body.get('TransCode', 0)) != 1:
        return '1|OK', 200, {'Content-Type': 'text/plain'}
    data   = aes_decrypt(body['Data'])        # AES 解密（格式與信用卡 ReturnURL 相同）
    rtn_code = int(data.get('RtnCode', 0))
    order_info = data.get('OrderInfo', {})
    trade_no = order_info.get('MerchantTradeNo', '')
    pay_type = order_info.get('PaymentType', '')    # 例：'ATM_TAISHIN', 'CVS_CVS'

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

## 延伸閱讀

| 子指南 | 內容 |
|--------|------|
| **本文（02a）** | 首次串接快速路徑、Python/Node.js 完整 E2E 範例 |
| [02b — ATM / CVS / SPA 整合](./02b-ecpg-atm-cvs-spa.md) | ATM/CVS 快速路徑、SPA/React/Vue 整合 |
| [02c — App / 正式環境](./02c-ecpg-app-production.md) | iOS/Android App 整合、Apple Pay、正式環境切換、**TransCode ≠ 1 錯誤降級處理**(所有環境適用) |
| [02 — 完整指南 Hub](./02-payment-ecpg.md) | 綁卡/退款/查詢/對帳/安全 |

> 💡 **正式環境 TransCode ≠ 1 降級策略**:雖然 `02c` 放在 App/正式環境章節,但該章節的「TransCode ≠ 1 錯誤降級」邏輯(伺服器時鐘偏差、負載高峰超時)**同樣適用於 Web 與 ATM/CVS 流程**。上線前務必閱讀 [guides/02c §3. TransCode≠1 錯誤降級](./02c-ecpg-app-production.md#3-transcode1-錯誤降級)。

