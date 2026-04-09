> 對應 ECPay API 版本 | 基於 PHP SDK ecpay/sdk | 最後更新：2026-03

> ⚠️ **SNAPSHOT 2026-03** | 對應 [guides/02 主指南](./02-payment-ecpg.md)

> 📖 本文件為 [guides/02 站內付 2.0 完整指南](./02-payment-ecpg.md) 的子指南 — App 整合 + 正式環境

## Web vs App 整合差異

⚠️ **Reference URL 對齊說明**：本指南主要基於 `references/Payment/站內付2.0API技術文件Web.md`，但部分內容可能參考 App 版本。後端 API（GetToken、CreatePayment、DoAction 等）**完全相同**，差異僅在前端整合方式。

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


## 相關文件

> ⚠️ **Web vs App 規格對齊說明**：`references/Payment/站內付2.0API技術文件Web.md` 和 `references/Payment/站內付2.0API技術文件App.md` 分別對應 Web 端與 App 端的 API 規格。兩者的 Server-to-Server API（GetTokenbyTrade、CreatePayment、QueryTrade、DoAction 等）參數完全相同；差異僅在前端 SDK 初始化方式（Web JS SDK vs iOS/Android SDK）。本指南主要以 Web 規格為準，App 端特殊事項請參考 App 規格文件。

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
// ⚠️ createPayment 使用 positional 參數：(token, language, callback, version)
window.ECPay.createPayment(currentToken, 'zh-TW', function(errMsg) {
  if (errMsg != null) {
    if (typeof errMsg === 'string' && errMsg.includes('Token')) {
      // Token 過期，重新呼叫後端取得新 Token + 新 MerchantTradeNo
      fetch('/api/get-token').then(r => r.json()).then(({ token, tradeNo }) => {
        updateToken(token, tradeNo);  // 更新前端持有的 Token
      });
    } else {
      console.error('createPayment 錯誤:', errMsg);
    }
  }
}, 'V2');
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
| JS SDK URL | `ecpg.ecpay.com.tw/Scripts/sdk-1.0.0.js`（⚠️ **測試/正式都用正式 domain**） | `ecpg.ecpay.com.tw/Scripts/sdk-1.0.0.js` |

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

---

## 延伸閱讀

| 子指南 | 內容 |
|--------|------|
| [02a — 首次串接快速路徑](./02a-ecpg-quickstart.md) | GetToken/CreatePayment 最快成功路徑、Python/Node.js 完整範例 |
| [02b — ATM / CVS / SPA 整合](./02b-ecpg-atm-cvs-spa.md) | ATM/CVS 快速路徑、SPA/React/Vue 整合 |
| **本文（02c）** | iOS/Android App 整合、Apple Pay、正式環境切換 |
| [02 — 完整指南 Hub](./02-payment-ecpg.md) | 綁卡/退款/查詢/對帳/安全 |
5. ✅ 若有 Apple Pay，在真實 Apple 裝置確認按鈕顯示
