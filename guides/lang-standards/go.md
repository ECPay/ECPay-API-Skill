# Go — ECPay 整合程式規範

> 本檔為 AI 生成 ECPay 整合程式碼時的 Go 專屬規範。
> 加密函式：[guides/13 §Go](../13-checkmacvalue.md) + [guides/14 §Go](../14-aes-encryption.md)
> E2E 範例：[guides/24 §Go（完整 Web Server）](../24-multi-language-integration.md)

## 版本與環境

- **最低版本**：Go 1.21+（`slices`、`slog` 標準庫）
- **推薦版本**：Go 1.22+
- **零外部依賴**：純標準庫即可完成 ECPay 串接（`net/http`、`crypto`、`encoding/json`）

## 命名慣例

```go
// 函式 / 方法：PascalCase（exported）或 camelCase（unexported）
func GenerateCheckMacValue(params map[string]string, hashKey, hashIV string) string { }
func ecpayURLEncode(s string) string { }  // 套件內部使用

// 結構體：PascalCase
type AIOParams struct { }

// 常數：PascalCase（Go 慣例，非 UPPER_SNAKE）
const EcpayPaymentURL = "https://payment.ecpay.com.tw/Cashier/AioCheckOut/V5"

// 套件名：全小寫、簡短
// package ecpay

// 檔案名：snake_case.go
// check_mac_value.go, aes.go, callback.go
```

## 推薦套件結構

```
ecpay/
├── ecpay.go          // 公開 API（NewClient, Pay, Query）
├── aes.go            // AES 加解密
├── cmv.go            // CheckMacValue 計算 + 驗證
├── url_encode.go     // ecpayURLEncode + aesURLEncode
├── types.go          // 所有型別定義
├── config.go         // 設定載入
└── ecpay_test.go     // 測試
```

## 型別定義

```go
package ecpay

// AIOParams AIO 金流送出參數
type AIOParams struct {
    MerchantID        string `json:"MerchantID"`
    MerchantTradeNo   string `json:"MerchantTradeNo"`
    MerchantTradeDate string `json:"MerchantTradeDate"`
    PaymentType       string `json:"PaymentType"`
    TotalAmount       string `json:"TotalAmount"`
    TradeDesc         string `json:"TradeDesc"`
    ItemName          string `json:"ItemName"`
    ReturnURL         string `json:"ReturnURL"`
    ChoosePayment     string `json:"ChoosePayment"`
    EncryptType       string `json:"EncryptType"`
    CheckMacValue     string `json:"CheckMacValue,omitempty"`
}

// AESRequest AES-JSON 請求外層
type AESRequest struct {
    MerchantID string      `json:"MerchantID"`
    RqHeader   RqHeader    `json:"RqHeader"`
    Data       string      `json:"Data"`
}

type RqHeader struct {
    Timestamp int64  `json:"Timestamp"`
    Revision  string `json:"Revision,omitempty"`
}

// AESResponse AES-JSON 回應外層
type AESResponse struct {
    TransCode int    `json:"TransCode"`
    TransMsg  string `json:"TransMsg"`
    Data      string `json:"Data"`
}

// CallbackParams AIO callback 參數（RtnCode 為字串）
type CallbackParams struct {
    MerchantID      string `json:"MerchantID"`
    MerchantTradeNo string `json:"MerchantTradeNo"`
    RtnCode         string `json:"RtnCode"`  // ⚠️ 字串
    RtnMsg          string `json:"RtnMsg"`
    TradeNo         string `json:"TradeNo"`
    TradeAmt        string `json:"TradeAmt"`
    PaymentDate     string `json:"PaymentDate"`
    PaymentType     string `json:"PaymentType"`
    CheckMacValue   string `json:"CheckMacValue"`
    SimulatePaid    string `json:"SimulatePaid"`
}

// Config ECPay 環境設定
type Config struct {
    MerchantID string
    HashKey    string
    HashIV     string
    BaseURL    string
}
```

## 錯誤處理

```go
import (
    "errors"
    "fmt"
)

// EcpayError ECPay API 錯誤
type EcpayError struct {
    TransCode int
    RtnCode   string
    Message   string
}

func (e *EcpayError) Error() string {
    return fmt.Sprintf("TransCode=%d, RtnCode=%s: %s", e.TransCode, e.RtnCode, e.Message)
}

var (
    ErrRateLimit = errors.New("ecpay: rate limited (403), retry after ~30 min")
    ErrCMVMismatch = errors.New("ecpay: CheckMacValue verification failed")
)

func CallAESAPI(ctx context.Context, url string, req AESRequest, hashKey, hashIV string) (map[string]interface{}, error) {
    // ... HTTP POST（使用 http.NewRequestWithContext(ctx, ...) ）...
    httpReq, err := http.NewRequestWithContext(ctx, "POST", url, body)
    if err != nil {
        return nil, fmt.Errorf("create request: %w", err)
    }
    resp, err := httpClient.Do(httpReq)
    if err != nil {
        return nil, fmt.Errorf("http post: %w", err)
    }
    defer resp.Body.Close()

    if resp.StatusCode == 403 {
        return nil, ErrRateLimit
    }

    var result AESResponse
    if err := json.NewDecoder(resp.Body).Decode(&result); err != nil {
        return nil, fmt.Errorf("decode response: %w", err)
    }

    // 雙層錯誤檢查
    if result.TransCode != 1 {
        return nil, &EcpayError{TransCode: result.TransCode, Message: result.TransMsg}
    }
    data, err := AesDecrypt(result.Data, hashKey, hashIV)
    if err != nil {
        return nil, fmt.Errorf("AES decrypt: %w", err)
    }
    if fmt.Sprintf("%v", data["RtnCode"]) != "1" {
        // 防禦性寫法：AES-JSON 服務（ECPG/發票）解密後 RtnCode 為 JSON number，
        // encoding/json 將其解析為 float64(1)。fmt.Sprintf("%v", float64(1)) = "1"（非 "1.0"），
        // 與 SKILL.md 規則 13「解密後為整數 1」一致；此寫法同時相容字串 "1"（防禦未來規格變動）。
        return nil, &EcpayError{TransCode: 1, RtnCode: fmt.Sprintf("%v", data["RtnCode"]),
            Message: fmt.Sprintf("%v", data["RtnMsg"])}
    }
    return data, nil
}
```

## HTTP Client 設定

```go
var httpClient = &http.Client{
    Timeout: 30 * time.Second,
    Transport: &http.Transport{
        MaxIdleConns:        100,
        MaxIdleConnsPerHost: 10,
        IdleConnTimeout:     90 * time.Second,
    },
}
// ⚠️ 使用全域 http.Client，勿每次請求 new 一個
// http.Client 為 goroutine-safe，可安全在多個 goroutine 間共用

// 呼叫端錯誤判斷：使用 errors.Is / errors.As
//   data, err := CallAESAPI(ctx, url, req, key, iv)
//   if errors.Is(err, ErrRateLimit) {
//       // 等待重試
//   }
//   var ecpayErr *EcpayError
//   if errors.As(err, &ecpayErr) {
//       log.Printf("ECPay error: TransCode=%d, RtnCode=%s", ecpayErr.TransCode, ecpayErr.RtnCode)
//   }
```

## Callback Handler 模板

```go
func handleCallback(w http.ResponseWriter, r *http.Request) {
    if err := r.ParseForm(); err != nil {
        http.Error(w, "Bad Request", http.StatusBadRequest)
        return
    }
    params := make(map[string]string)
    for k, v := range r.PostForm {
        params[k] = v[0]
    }

    // 1. Timing-safe CMV 驗證（需 import "crypto/subtle"）
    receivedCMV := params["CheckMacValue"]
    delete(params, "CheckMacValue")
    expectedCMV := GenerateCheckMacValue(params, hashKey, hashIV)
    if subtle.ConstantTimeCompare([]byte(receivedCMV), []byte(expectedCMV)) != 1 {
        http.Error(w, "CheckMacValue Error", http.StatusBadRequest)
        return
    }

    // 2. RtnCode 是字串
    if params["RtnCode"] == "1" {
        // 處理成功
    }

    // 3. HTTP 200 + "1|OK"
    w.Header().Set("Content-Type", "text/plain")
    w.WriteHeader(http.StatusOK)
    fmt.Fprint(w, "1|OK")
}
```

## JSON 序列化注意

```go
// ⚠️ json.Marshal 會轉義 <, >, & 為 \uXXXX — ECPay 可能不接受
// 必須用 json.NewEncoder + SetEscapeHTML(false)
var buf bytes.Buffer
encoder := json.NewEncoder(&buf)
encoder.SetEscapeHTML(false)
encoder.Encode(data)
jsonStr := strings.TrimRight(buf.String(), "\n")

// ⚠️ map[string]interface{} 的 key 會按字母序排列
// 若需保證插入順序，使用 struct
```

> ⚠️ ECPay Callback URL 僅支援 port 80 (HTTP) / 443 (HTTPS)，開發環境使用 ngrok 轉發到本機任意 port。

## 日期與時區

```go
import "time"

// ⚠️ ECPay 所有時間欄位皆為台灣時間（UTC+8）
var twLoc = time.FixedZone("Asia/Taipei", 8*60*60)

// MerchantTradeDate 格式：yyyy/MM/dd HH:mm:ss（非 ISO 8601）
// Go 使用 reference time "2006/01/02 15:04:05"
func merchantTradeDate() string {
    return time.Now().In(twLoc).Format("2006/01/02 15:04:05")
    // → "2026/03/11 12:10:41"
}

// AES RqHeader.Timestamp：Unix 秒數
timestamp := time.Now().Unix() // int64，已為秒數
```

## 環境變數

```go
import "os"

type Config struct { /* ... */ }

func LoadConfig() Config {
    env := os.Getenv("ECPAY_ENV")
    baseURL := "https://payment-stage.ecpay.com.tw"
    if env == "production" {
        baseURL = "https://payment.ecpay.com.tw"
    }
    return Config{
        MerchantID: os.Getenv("ECPAY_MERCHANT_ID"),
        HashKey:    os.Getenv("ECPAY_HASH_KEY"),
        HashIV:     os.Getenv("ECPAY_HASH_IV"),
        BaseURL:    baseURL,
    }
}
```

## 日誌與監控

```go
import "log/slog"

// 推薦 slog（Go 1.21+ 標準庫結構化日誌）
logger := slog.Default()

// ⚠️ 絕不記錄 HashKey / HashIV / CheckMacValue
// ✅ 記錄：API 呼叫結果、交易編號、錯誤訊息
logger.Info("ECPay API 呼叫成功", "merchantTradeNo", merchantTradeNo)
logger.Error("ECPay API 錯誤", "transCode", result.TransCode, "rtnCode", rtnCode)
```

> **日誌安全規則**：HashKey、HashIV、CheckMacValue 為機敏資料，嚴禁出現在任何日誌、錯誤回報或前端回應中。

## URL Encode 注意

```go
// ⚠️ Go 的 url.QueryEscape() 不會編碼 ~ 字元
// ECPay CheckMacValue 要求 ~ 編碼為 %7e
// guides/13 的 ecpayURLEncode 已處理此轉換（~ → %7e）
// 請直接使用 guides/13 提供的函式，勿自行實作
```

## 單元測試模式

```go
// ecpay_test.go
package ecpay

import "testing"

func TestCMVSHA256(t *testing.T) {
    params := map[string]string{
        "MerchantID": "3002607",
        // ... test vector params ...
    }
    got := GenerateCheckMacValue(params, "pwFHCqoQZGmho4w6", "EkRm7iFT261dpevs")
    want := "291CBA324D31FB5A4BBBFDF2CFE5D32598524753AFD4959C3BF590C5B2F57FB2"
    if got != want {
        t.Errorf("CMV = %s, want %s", got, want)
    }
}

func TestAESRoundtrip(t *testing.T) {
    data := map[string]interface{}{"MerchantID": "2000132", "BarCode": "/1234567"}
    encrypted, err := AesEncrypt(data, "ejCk326UnaZWKisg", "q9jcZX8Ib9LM8wYk")
    if err != nil { t.Fatal(err) }
    decrypted, err := AesDecrypt(encrypted, "ejCk326UnaZWKisg", "q9jcZX8Ib9LM8wYk")
    if err != nil { t.Fatal(err) }
    if decrypted["MerchantID"] != "2000132" { t.Fail() }
}
```

```bash
go test ./... -race -cover
# 推薦使用 golangci-lint
```
