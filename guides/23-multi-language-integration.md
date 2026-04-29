> 對應 ECPay API 版本 | 最後更新：2026-04

<!-- AI Section Index（精確行號，2026-04-23 校準）
Go E2E: line 136-488 (CMV: 138-270, AES-CBC: 271-488)
Java E2E + 差異指南: line 491-709 (CMV E2E: 493-667, AES-CBC 差異: 668-709)
C# E2E + 差異指南: line 711-872 (CMV E2E: 713-832, AES-CBC 差異: 833-872)
TypeScript 完整 E2E + 型別定義: line 874-1123
Kotlin 差異指南: line 1126-1169 | Ruby 差異指南: line 1171-1212
Swift 差異指南: line 1214-1254 | Rust 差異指南: line 1256-1297
Mobile App: line 1299-1354 | 非 PHP CMV Checklist: line 1356-1374
非 PHP AES-JSON Checklist: line 1376-1393
E2E 組裝步驟: line 1395-1406 | C/C++ 注意事項: line 1408-1675
跨語言測試: line 1677-1683 | 電子收據 AES-GCM E2E（V3.0+）: line 1685-2022
  ├ Go 開立完整 E2E: line 1692-1842
  ├ TypeScript 開立完整 E2E: line 1844-1952
  └ TypeScript Callback Handler: line 1954-2022
Production 環境切換: line 2024+ | 相關文件: 檔尾
-->

# 多語言整合完整指南

> 💡 本文以 Go 為 E2E 完整範例語言。Python、Node.js、TypeScript 的完整範例請參閱 [guides/13（CheckMacValue）](./13-checkmacvalue.md) 與 [guides/14（AES 加解密）](./14-aes-encryption.md) 中的對應語言區段。

> 📌 **語言規範**：生成目標語言程式碼時，同時載入 `guides/lang-standards/{語言}.md`（命名慣例、型別定義、錯誤處理、HTTP 設定等），確保產出的程式碼為 idiomatic 且生產就緒。

## 何時需要本指南？

> 你的 Protocol 決定了你需要什麼：

| 你要串接的服務 | Protocol | 核心依賴 | 本指南作用 |
|------------|:---:|---------|---------|
| AIO 金流（消費者跳轉付款頁）| CMV-SHA256 | [guides/13 CheckMacValue](./13-checkmacvalue.md) | 提供 Go/Java/C# E2E + 其他語言差異指南 |
| ECPG 站內付 2.0（嵌入付款）| AES-JSON | [guides/14 AES](./14-aes-encryption.md) | 同上 |
| 電子發票、全方位物流 v2 | AES-JSON | [guides/14 AES](./14-aes-encryption.md) | 同上 |
| 國內物流 | CMV-MD5 | [guides/13 CheckMacValue](./13-checkmacvalue.md) + MD5 | guides/06 為主，本指南補語言差異 |
| ECTicket | AES-JSON + CMV | 兩者皆需 | Go E2E + 語言差異指南 |

> **只需加密函式（不需 HTTP 流程）？** → [guides/13](./13-checkmacvalue.md) 或 [guides/14](./14-aes-encryption.md)（12 語言全覆蓋）直接使用

> 🎯 **快速使用本指南**：使用下方行號直接跳轉到你的語言，不需閱讀全文。

## 語言快速導航

| 語言 | CMV-SHA256 (AIO) | AES-JSON (發票) | 類型 | 位置 / 行號 |
|------|:-:|:-:|:-:|---------|
| **Go** | ✅ Web Server | ✅ B2C 發票 | 完整 E2E | line 136-488 |
| **Java** | ✅ Web Server | ✅ | E2E + 差異指南 | line 491-709 |
| **C#** | ✅ Web Server | ✅ | E2E + 差異指南 | line 711-872 |
| **TypeScript** | ✅ Web Server | → Node.js | 完整範例 | line 874-1123 |
| **Kotlin** | ✅ | ✅ | 差異指南 | line 1126-1169 |
| **Ruby** | ✅ | ✅ | 差異指南 | line 1171-1212 |
| **Swift** | ✅ | ✅ | 差異指南 | line 1214-1254 |
| **Rust** | ✅ | ✅ | 差異指南 | line 1256-1297 |
| **Python** | ✅ | ✅ | 完整 E2E | → [guides/00](./00-getting-started.md) §Quick Start |
| **Node.js** | ✅ | ✅ | 完整 E2E | → [guides/00](./00-getting-started.md) §Quick Start |
| **Mobile App** | — | — | iOS + Android 指引 | line 1299-1354 |
| **C/C++** | ✅ minimal | ✅ minimal | 最小骨架 | line 1408-1675 |

> **只需看你的語言**：使用 AI Section Index 行號範圍只讀取對應區段，不需載入全文。
> **只需加密函式？** → [guides/13 CheckMacValue](./13-checkmacvalue.md) 或 [guides/14 AES](./14-aes-encryption.md)（12 語言全覆蓋）。

### 設計原則

- **Go/Java/C#/TypeScript** 提供完整 E2E 範例——這四種語言在 ECPay 串接中使用率最高且生態系成熟，因此優先提供可直接執行的完整範例
- **Kotlin/Ruby/Swift/Rust** 提供差異指南——與 Go 基底的關鍵差異（依賴、API 差異、注意事項），搭配 guides/13-14 語言專屬加密函式組裝完整實作
- **Python/Node.js** 完整 E2E 已在 [guides/00](./00-getting-started.md) §Quick Start 提供
- **C/C++** 提供最小骨架——因系統層級語言的串接場景較少，僅提供核心加密 + HTTP 框架
- AI 生成其他語言程式碼時，會基於本指南的 E2E 結構 + guides/13-14 的加密實作 + guides/19 的 HTTP 協議規格進行翻譯

> ### ⚠️ AI 組裝指令（差異指南使用規則）
>
> Kotlin/Ruby/Swift/Rust 區段僅列出與 Go 的差異，**不是可獨立執行的程式碼**。
> 生成完整實作時必須遵守：
>
> 1. **加密函式禁止從 Go 直譯** — 必須使用 [guides/13](./13-checkmacvalue.md) 和 [guides/14](./14-aes-encryption.md) 中該語言的專屬實作（含 URL encode 差異、padding 處理、JSON 序列化注意事項）
> 2. **組裝順序**：先讀 guides/13-14 目標語言區段 → 再讀本文 Go E2E 了解業務流程 → 最後套用差異指南的依賴和 API 差異
> 3. **必驗項目**：用 `test-vectors/` 驗證加密輸出正確性，再整合到 E2E 流程
>
> Java、C# 已提供完整 E2E，可直接執行（見 line 471 和 691）。TypeScript 建議使用下方完整範例。

## 概述

本指南為非 PHP/Node.js/Python 開發者提供完整的 ECPay API 整合範例，涵蓋 Go、Java、C#、Kotlin 等語言的端到端實作。

> **Python / Node.js 開發者**：你的 Quick Start 和 AES-JSON 端到端範例已在 guides/00-getting-started.md 提供。
> - CMV-SHA256 AIO Quick Start：guides/00 §Quick Start
> - AES-JSON 發票完整範例：guides/00 §AES-JSON 端到端範例
> - CheckMacValue 完整實作：guides/13 §Python / §Node.js
> - AES 加密/解密完整實作：guides/14 §Python / §Node.js
>
> ⚠️ **遇到加密問題需要自行除錯時，必須讀 [guides/13](./13-checkmacvalue.md)（CheckMacValue 完整實作 + 測試向量）和 [guides/14](./14-aes-encryption.md)（AES 完整實作 + 常見錯誤）**，Quick Start 範例不含完整的錯誤排查函式。

**前置條件**：
- 已讀 [guides/19-http-protocol-reference.md](./19-http-protocol-reference.md)（HTTP 協議規格）
- 已讀 [guides/13-checkmacvalue.md](./13-checkmacvalue.md)（CMV-SHA256/CMV-MD5 認證）或 [guides/14-aes-encryption.md](./14-aes-encryption.md)（AES-JSON 認證）

**涵蓋語言**：Go（完整 E2E）、Java/C#/Kotlin/Ruby/Swift/Rust（差異指南）、TypeScript（型別定義）+ 全 12 語言通用參考

## HTTP Client 推薦表

| 語言 | 推薦 Client | 最低版本 | 安裝命令 | Timeout 設定 |
|------|------------|---------|---------|-------------|
| Go | net/http (stdlib) | Go 1.21+ | — | `client.Timeout = 30 * time.Second` |
| Java | java.net.http.HttpClient | JDK 11+ | — | `connectTimeout(Duration.ofSeconds(30))` |
| C# | HttpClient | .NET 6+ | — | `Timeout = TimeSpan.FromSeconds(30)` |
| Node.js | built-in fetch / axios | Node 18+ / axios 1.7+ | `npm install axios` | `signal: AbortSignal.timeout(30000)` |
| Python | httpx (async) / requests (sync) | httpx 0.27+ / requests 2.31+ | `pip install httpx` | `timeout=30.0` |
| Rust | reqwest | 0.12+ | `cargo add reqwest` | `timeout(Duration::from_secs(30))` |
| Swift | URLSession | iOS 13+ / macOS 10.15+ | — | `timeoutIntervalForRequest = 30` |
| Kotlin | java.net.http.HttpClient | JDK 11+ | — | `connectTimeout(Duration.ofSeconds(30))` |
| Ruby | Net::HTTP (stdlib) | Ruby 3.1+ | — | `open_timeout = 30; read_timeout = 30` |
| C | libcurl | 8.0+ | 系統套件管理器 | `CURLOPT_TIMEOUT 30L` |
| C++ | cpr | 1.10+ | `vcpkg install cpr` 或 CMake FetchContent | `cpr::Timeout{30000}` |

> **所有語言共通**：ECPay API 收到 403 表示觸發限流，需等待約 30 分鐘。建議 API 呼叫間隔至少 200ms。

## JSON 序列化全語言對照表

> ⚠️ **通用警告**：AES-JSON 的 AES 加密結果取決於 JSON 字串的精確位元內容。
> 不同的 key 順序、空格、HTML 轉義都會產生不同的密文，導致 ECPay API 解密失敗。
> 必須確保 JSON 輸出為 compact 格式（無多餘空格），且 key 順序與預期一致。

| 語言 | 函式 | Key 順序保證 | Compact 模式 | HTML 轉義 | 必要設定 |
|------|------|:----------:|:----------:|:---------:|---------|
| PHP | `json_encode()` | 依插入順序 | 預設 compact | 預設不轉義 | 無需特殊設定（基準實作） |
| Python | `json.dumps()` | dict 依插入順序 (3.7+) | 需設定 | 預設不轉義 | `separators=(',', ':'), ensure_ascii=False` |
| Node.js | `JSON.stringify()` | 依插入順序 | 預設 compact | 預設不轉義 | 無需特殊設定 |
| Go | `json.Marshal()` | struct: 欄位定義順序; map: 字母序 | 預設 compact | **預設轉義** `<>&` | `json.NewEncoder(buf)` + `SetEscapeHTML(false)` |
| Java | `Gson` | HashMap **不保證**順序 | 預設 compact | **預設轉義** | `GsonBuilder().disableHtmlEscaping()` + 用 `LinkedHashMap` 保序 |
| C# | `System.Text.Json` | class 屬性定義順序 | 預設 compact | **預設轉義** `<>&+'` | `UnsafeRelaxedJsonEscaping` + class 屬性順序（見 lang-standards/csharp.md） |
| Kotlin | `Gson` | HashMap **不保證**順序 | 預設 compact | **預設轉義** | `GsonBuilder().disableHtmlEscaping()` + 用 `linkedMapOf()` 保序 |
| Swift | `JSONEncoder` | 預設不保證 | 預設 compact | 預設不轉義 | 設定 `.sortedKeys`；或用 `Codable` struct |
| Ruby | `JSON.generate()` | Hash 依插入順序 (1.9+) | 預設 compact | 預設不轉義 | 勿用 `pretty_generate`；用 `JSON.generate(data)` |
| Rust | `serde_json` | struct: 欄位定義順序; Map: 依實作 | 預設 compact | 預設不轉義 | 用 struct 確保欄位順序穩定 |
| C | `cJSON` | 依新增順序 | 預設 compact | 預設不轉義 | 用 `cJSON_PrintUnformatted()` |
| C++ | `nlohmann/json` | ordered_json 依插入順序 | 預設 compact | 預設不轉義 | 用 `nlohmann::ordered_json` + `dump()` |

---

## Go 完整整合範例

### CMV-SHA256 — AIO 信用卡付款（完整 Web Server）

> 對應 PHP 範例：`scripts/SDK_PHP/example/Payment/Aio/CreateCreditOrder.php`

```
go.mod:
  module ecpay-demo
  go 1.21
```

```go
package main

import (
	"crypto/sha256"
	"crypto/subtle"
	"fmt"
	"net/http"
	"net/url"
	"sort"
	"strings"
	"time"
)

const (
	merchantID = "3002607"           // 測試用：正式環境改用 os.Getenv("ECPAY_MERCHANT_ID")
	hashKey    = "pwFHCqoQZGmho4w6"  // 測試用：正式環境改用 os.Getenv("ECPAY_HASH_KEY")
	hashIV     = "EkRm7iFT261dpevs"  // 測試用：正式環境改用 os.Getenv("ECPAY_HASH_IV")
	aioURL     = "https://payment-stage.ecpay.com.tw/Cashier/AioCheckOut/V5"
)

// 完整實作見 guides/13-checkmacvalue.md §Go
// ecpayURLEncode 實作 ECPay 專用的 URL encode（參考 guides/13-checkmacvalue.md）
func ecpayURLEncode(s string) string {
	encoded := url.QueryEscape(s) // 空格→+
	encoded = strings.ToLower(encoded)
	r := strings.NewReplacer("%2d", "-", "%5f", "_", "%2e", ".", "%21", "!", "%2a", "*", "%28", "(", "%29", ")")
	encoded = r.Replace(encoded)
	encoded = strings.ReplaceAll(encoded, "~", "%7e")
	encoded = strings.ReplaceAll(encoded, "'", "%27") // Go url.QueryEscape 不編碼 '，但 PHP urlencode 會
	return encoded
}

// generateCheckMacValue 產生 SHA256 CheckMacValue
func generateCheckMacValue(params map[string]string) string {
	keys := make([]string, 0, len(params))
	for k := range params {
		if k == "CheckMacValue" {
			continue
		}
		keys = append(keys, k)
	}
	sort.SliceStable(keys, func(i, j int) bool {
		return strings.ToLower(keys[i]) < strings.ToLower(keys[j])
	})

	var pairs []string
	for _, k := range keys {
		pairs = append(pairs, fmt.Sprintf("%s=%s", k, params[k]))
	}
	raw := fmt.Sprintf("HashKey=%s&%s&HashIV=%s", hashKey, strings.Join(pairs, "&"), hashIV)
	encoded := ecpayURLEncode(raw)
	hash := sha256.Sum256([]byte(encoded))
	return fmt.Sprintf("%X", hash)
}

func checkoutHandler(w http.ResponseWriter, r *http.Request) {
	tradeNo := fmt.Sprintf("Go%d", time.Now().Unix())
	tradeDate := time.Now().Format("2006/01/02 15:04:05")

	params := map[string]string{
		"MerchantID":        merchantID,
		"MerchantTradeNo":   tradeNo,
		"MerchantTradeDate": tradeDate,
		"PaymentType":       "aio",
		"TotalAmount":       "100",
		"TradeDesc":         "測試交易",
		"ItemName":          "測試商品",
		"ReturnURL":         "https://your-domain.com/ecpay/notify", // ⚠️ 必須替換：填入你的公開回呼 URL
		"ChoosePayment":     "Credit",
		"EncryptType":       "1",
	}
	params["CheckMacValue"] = generateCheckMacValue(params)

	// 產生自動提交表單
	var fields strings.Builder
	for k, v := range params {
		// ⚠️ 正式環境需對 k, v 進行 html.EscapeString() 防止 XSS
		fields.WriteString(fmt.Sprintf(`<input type="hidden" name="%s" value="%s">`, k, v))
	}
	w.Header().Set("Content-Type", "text/html; charset=utf-8")
	fmt.Fprintf(w, `<form id="ecpay" method="POST" action="%s">%s</form>
<script>document.getElementById('ecpay').submit();</script>`, aioURL, fields.String())
}

func notifyHandler(w http.ResponseWriter, r *http.Request) {
	if err := r.ParseForm(); err != nil {
		fmt.Fprint(w, "0|ParseError")
		return
	}
	params := make(map[string]string)
	for k, v := range r.PostForm {
		params[k] = v[0]
	}

	// 驗證 CheckMacValue
	receivedCMV := params["CheckMacValue"]
	calculatedCMV := generateCheckMacValue(params)
	if subtle.ConstantTimeCompare([]byte(receivedCMV), []byte(calculatedCMV)) != 1 {
		fmt.Fprint(w, "0|CheckMacValue Error")
		return
	}

	if params["RtnCode"] == "1" && params["SimulatePaid"] == "0" {
		// 真實付款成功，處理訂單邏輯
		fmt.Printf("付款成功: %s\n", params["MerchantTradeNo"])
	}

	// 必須回應 1|OK
	fmt.Fprint(w, "1|OK")
}

func main() {
	http.HandleFunc("/checkout", checkoutHandler)
	http.HandleFunc("/ecpay/notify", notifyHandler)
	fmt.Println("Server: http://localhost:3000/checkout")
	http.ListenAndServe(":3000", nil)
}
```

**執行**：`go run main.go`，瀏覽 `http://localhost:3000/checkout`。
使用測試信用卡 `4311-9522-2222-2222`，CVV `222`，3D 驗證碼 `1234`。

### AES-JSON — B2C 發票開立

> 對應 PHP 範例：`scripts/SDK_PHP/example/Invoice/B2C/Issue.php`

```go
package main

import (
	"bytes"
	"crypto/aes"
	"crypto/cipher"
	"encoding/base64"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"net/url"
	"strings"
	"time"
)

const (
	invoiceMerchantID = "2000132"          // 測試用：正式環境改用 os.Getenv("ECPAY_MERCHANT_ID")
	invoiceHashKey    = "ejCk326UnaZWKisg" // 測試用：正式環境改用 os.Getenv("ECPAY_HASH_KEY")
	invoiceHashIV     = "q9jcZX8Ib9LM8wYk" // 測試用：正式環境改用 os.Getenv("ECPAY_HASH_IV")
	invoiceURL        = "https://einvoice-stage.ecpay.com.tw/B2CInvoice/Issue"
)

func pkcs7Pad(data []byte, blockSize int) []byte {
	padding := blockSize - len(data)%blockSize
	padText := bytes.Repeat([]byte{byte(padding)}, padding)
	return append(data, padText...)
}

func pkcs7Unpad(data []byte) ([]byte, error) {
	if len(data) == 0 {
		return nil, fmt.Errorf("empty data")
	}
	padding := int(data[len(data)-1])
	if padding < 1 || padding > aes.BlockSize || padding > len(data) {
		return nil, fmt.Errorf("invalid padding")
	}
	return data[:len(data)-padding], nil
}

// 完整實作見 guides/13-checkmacvalue.md §Go
// ecpayURLEncode 同上方 CMV-SHA256 範例
func ecpayURLEncode(s string) string {
	encoded := url.QueryEscape(s)
	encoded = strings.ToLower(encoded)
	replacer := strings.NewReplacer(
		"%2d", "-", "%5f", "_", "%2e", ".",
		"%21", "!", "%2a", "*", "%28", "(", "%29", ")",
	)
	encoded = replacer.Replace(encoded)
	encoded = strings.ReplaceAll(encoded, "~", "%7e")
	encoded = strings.ReplaceAll(encoded, "'", "%27") // Go url.QueryEscape 不編碼 '，但 PHP urlencode 會
	return encoded
}

// 完整實作見 guides/14-aes-encryption.md §Go
// AES 專用 URL encode — 不做 toLowerCase 和 .NET 還原（與 CMV ecpayURLEncode 不同）
// url.QueryEscape("~") = "~"（RFC 3986 unreserved），但 PHP urlencode("~") = "%7E"，需手動補
func aesURLEncode(s string) string {
	encoded := url.QueryEscape(s)
	// Go url.QueryEscape 不編碼 !'()*~，但 PHP urlencode 會，需手動補齊
	encoded = strings.ReplaceAll(encoded, "~", "%7E")
	encoded = strings.ReplaceAll(encoded, "!", "%21")
	encoded = strings.ReplaceAll(encoded, "*", "%2A")
	encoded = strings.ReplaceAll(encoded, "'", "%27")
	encoded = strings.ReplaceAll(encoded, "(", "%28")
	encoded = strings.ReplaceAll(encoded, ")", "%29")
	return encoded
}

func aesEncrypt(data interface{}, hashKey, hashIV string) (string, error) {
	// 使用 json.NewEncoder + SetEscapeHTML(false) 避免 <, >, & 被轉義
	var buf bytes.Buffer
	encoder := json.NewEncoder(&buf)
	encoder.SetEscapeHTML(false)
	if err := encoder.Encode(data); err != nil {
		return "", err
	}
	// Encode 會加 \n，需移除
	jsonStr := strings.TrimRight(buf.String(), "\n")

	urlEncoded := aesURLEncode(jsonStr)

	key := []byte(hashKey)[:16]
	iv := []byte(hashIV)[:16]
	block, err := aes.NewCipher(key)
	if err != nil {
		return "", err
	}
	padded := pkcs7Pad([]byte(urlEncoded), aes.BlockSize)
	encrypted := make([]byte, len(padded))
	cipher.NewCBCEncrypter(block, iv).CryptBlocks(encrypted, padded)
	return base64.StdEncoding.EncodeToString(encrypted), nil
}

func aesDecrypt(cipherText, hashKey, hashIV string) (map[string]interface{}, error) {
	encrypted, err := base64.StdEncoding.DecodeString(cipherText)
	if err != nil {
		return nil, err
	}
	key := []byte(hashKey)[:16]
	iv := []byte(hashIV)[:16]
	block, err := aes.NewCipher(key)
	if err != nil {
		return nil, err
	}
	decrypted := make([]byte, len(encrypted))
	cipher.NewCBCDecrypter(block, iv).CryptBlocks(decrypted, encrypted)
	unpadded, err := pkcs7Unpad(decrypted)
	if err != nil {
		return nil, err
	}
	urlDecoded, err := url.QueryUnescape(string(unpadded))
	if err != nil {
		return nil, err
	}
	var result map[string]interface{}
	err = json.Unmarshal([]byte(urlDecoded), &result)
	return result, err
}

func issueInvoice() error {
	invoiceData := map[string]interface{}{
		"MerchantID":    invoiceMerchantID,
		"RelateNumber":  fmt.Sprintf("INV%d", time.Now().Unix()),
		"CustomerEmail": "test@example.com",
		"Print":         "0",
		"Donation":      "0",
		"TaxType":       "1",
		"SalesAmount":   100,
		"Items": []map[string]interface{}{
			{
				"ItemName":    "測試商品",
				"ItemCount":   1,
				"ItemWord":    "件",
				"ItemPrice":   100,
				"ItemTaxType": "1",
				"ItemAmount":  100,
			},
		},
		"InvType": "07",
	}

	encryptedData, err := aesEncrypt(invoiceData, invoiceHashKey, invoiceHashIV)
	if err != nil {
		return fmt.Errorf("加密失敗: %w", err)
	}

	requestBody := map[string]interface{}{
		"MerchantID": invoiceMerchantID,
		"RqHeader": map[string]interface{}{
			"Timestamp": time.Now().Unix(),
			"Revision":  "3.0.0",
		},
		"Data": encryptedData,
	}

	bodyBytes, err := json.Marshal(requestBody)
	if err != nil {
		return err
	}

	client := &http.Client{Timeout: 30 * time.Second}
	resp, err := client.Post(invoiceURL, "application/json", bytes.NewReader(bodyBytes))
	if err != nil {
		return fmt.Errorf("HTTP 請求失敗: %w", err)
	}
	defer resp.Body.Close()

	respBody, err := io.ReadAll(resp.Body)
	if err != nil {
		return err
	}

	var result map[string]interface{}
	if err := json.Unmarshal(respBody, &result); err != nil {
		return fmt.Errorf("回應解析失敗: %w", err)
	}

	// 雙層錯誤檢查
	transCode, _ := result["TransCode"].(float64)
	if transCode != 1 {
		return fmt.Errorf("外層錯誤 TransCode=%.0f: %v", transCode, result["TransMsg"])
	}

	data, err := aesDecrypt(result["Data"].(string), invoiceHashKey, invoiceHashIV)
	if err != nil {
		return fmt.Errorf("解密失敗: %w", err)
	}

	rtnCode, _ := data["RtnCode"].(float64)
	if rtnCode != 1 {
		return fmt.Errorf("業務錯誤 RtnCode=%.0f: %v", rtnCode, data["RtnMsg"])
	}

	fmt.Printf("發票號碼: %v\n", data["InvoiceNo"])
	return nil
}

func main() {
	if err := issueInvoice(); err != nil {
		fmt.Printf("發票開立失敗: %v\n", err)
	}
}
```

**執行**：`go run invoice.go`

> **⚠️ AES vs CMV URL Encode 差異**：`ecpayURLEncode`（CMV 用）會做 toLowerCase + .NET 字元替換；
> `aesURLEncode`（AES 加密用）只做標準 `urlencode` + `~→%7E`。混用是常見錯誤。
> 詳見 [guides/14-aes-encryption.md](./14-aes-encryption.md)。

---


## Java 完整整合範例 + 差異指南

### CMV-SHA256 — AIO 信用卡付款（完整 Web Server）

> 對應 PHP 範例：`scripts/SDK_PHP/example/Payment/Aio/CreateCreditOrder.php`
> 加密函式取自 [guides/13 §Java](./13-checkmacvalue.md)，不可從 Go 直譯。

> ⚠️ **ECPG 雙 Domain**：Token/建立交易 API 走 `ecpg(-stage).ecpay.com.tw`，查詢/退款走 `ecpayment(-stage).ecpay.com.tw`，混用導致 404。詳見 [guides/02](./02-payment-ecpg.md)。

```java
// EcpayAioDemo.java — JDK 11+, 零外部依賴
import com.sun.net.httpserver.HttpServer;
import com.sun.net.httpserver.HttpExchange;

import java.io.IOException;
import java.io.InputStream;
import java.io.OutputStream;
import java.net.InetSocketAddress;
import java.net.URLDecoder;
import java.net.URLEncoder;
import java.nio.charset.StandardCharsets;
import java.security.MessageDigest;
import java.time.ZoneId;
import java.time.ZonedDateTime;
import java.time.format.DateTimeFormatter;
import java.util.LinkedHashMap;
import java.util.Map;
import java.util.StringJoiner;
import java.util.TreeMap;

public class EcpayAioDemo {

    // 測試用：正式環境改用 System.getenv("ECPAY_MERCHANT_ID") 等
    static final String MERCHANT_ID = "3002607";
    static final String HASH_KEY    = "pwFHCqoQZGmho4w6";
    static final String HASH_IV     = "EkRm7iFT261dpevs";
    static final String AIO_URL     = "https://payment-stage.ecpay.com.tw/Cashier/AioCheckOut/V5";

    // ECPay 專用 URL encode（完整實作見 guides/13 §Java）
    // (1) URLEncoder.encode (2) toLowerCase (3) 還原 .NET 保留字元
    static String ecpayUrlEncode(String s) throws Exception {
        String encoded = URLEncoder.encode(s, StandardCharsets.UTF_8);
        encoded = encoded.toLowerCase();
        return encoded
            .replace("%2d", "-").replace("%5f", "_").replace("%2e", ".")
            .replace("%21", "!").replace("%2a", "*")
            .replace("%28", "(").replace("%29", ")")
            .replace("~", "%7e");
    }

    // 產生 SHA256 CheckMacValue
    static String generateCheckMacValue(Map<String, String> params,
                                        String hashKey, String hashIV) throws Exception {
        TreeMap<String, String> sorted = new TreeMap<>(String.CASE_INSENSITIVE_ORDER);
        params.forEach((k, v) -> {
            if (!"CheckMacValue".equals(k)) sorted.put(k, v);
        });
        StringJoiner sj = new StringJoiner("&");
        sorted.forEach((k, v) -> sj.add(k + "=" + v));
        String raw = "HashKey=" + hashKey + "&" + sj + "&HashIV=" + hashIV;
        String encoded = ecpayUrlEncode(raw);
        MessageDigest md = MessageDigest.getInstance("SHA-256");
        byte[] digest = md.digest(encoded.getBytes(StandardCharsets.UTF_8));
        StringBuilder sb = new StringBuilder();
        for (byte b : digest) sb.append(String.format("%02x", b));
        return sb.toString().toUpperCase();
    }

    // 解析 application/x-www-form-urlencoded body
    static Map<String, String> parseFormBody(InputStream is) throws IOException {
        String body = new String(is.readAllBytes(), StandardCharsets.UTF_8);
        Map<String, String> map = new LinkedHashMap<>();
        if (body.isEmpty()) return map;
        for (String pair : body.split("&")) {
            int idx = pair.indexOf('=');
            if (idx < 0) continue;
            String key = URLDecoder.decode(pair.substring(0, idx), StandardCharsets.UTF_8);
            String val = URLDecoder.decode(pair.substring(idx + 1), StandardCharsets.UTF_8);
            map.put(key, val);
        }
        return map;
    }

    // GET /checkout — 產生自動提交表單，導向 ECPay 付款頁面
    static void checkoutHandler(HttpExchange ex) throws Exception {
        String tradeNo = "ecpay" + System.currentTimeMillis() / 1000;
        String tradeDate = ZonedDateTime.now(ZoneId.of("Asia/Taipei"))
            .format(DateTimeFormatter.ofPattern("yyyy/MM/dd HH:mm:ss"));

        Map<String, String> params = new LinkedHashMap<>();
        params.put("MerchantID",        MERCHANT_ID);
        params.put("MerchantTradeNo",   tradeNo);
        params.put("MerchantTradeDate", tradeDate);
        params.put("PaymentType",       "aio");
        params.put("TotalAmount",       "100");
        params.put("TradeDesc",         "test");
        params.put("ItemName",          "TestItem");
        params.put("ReturnURL",         "http://localhost:3000/ecpay/notify");
        params.put("ChoosePayment",     "Credit");
        params.put("EncryptType",       "1");
        params.put("CheckMacValue",
            generateCheckMacValue(params, HASH_KEY, HASH_IV));

        // 產生自動提交 HTML 表單
        StringBuilder fields = new StringBuilder();
        for (var entry : params.entrySet()) {
            // ⚠️ 正式環境需對 value 進行 HTML escape 防止 XSS
            fields.append(String.format(
                "<input type=\"hidden\" name=\"%s\" value=\"%s\">",
                entry.getKey(), entry.getValue()));
        }
        String html = String.format(
            "<form id=\"ecpay\" method=\"POST\" action=\"%s\">%s</form>" +
            "<script>document.getElementById('ecpay').submit();</script>",
            AIO_URL, fields);
        byte[] resp = html.getBytes(StandardCharsets.UTF_8);
        ex.getResponseHeaders().add("Content-Type", "text/html; charset=utf-8");
        ex.sendResponseHeaders(200, resp.length);
        try (OutputStream os = ex.getResponseBody()) { os.write(resp); }
    }

    // POST /ecpay/notify — 接收 ECPay 付款結果通知
    static void notifyHandler(HttpExchange ex) throws Exception {
        Map<String, String> params = parseFormBody(ex.getRequestBody());
        String received = params.getOrDefault("CheckMacValue", "");
        String calculated = generateCheckMacValue(params, HASH_KEY, HASH_IV);

        // timing-safe 比對
        boolean valid = MessageDigest.isEqual(
            received.getBytes(StandardCharsets.UTF_8),
            calculated.getBytes(StandardCharsets.UTF_8));

        String reply;
        if (!valid) {
            reply = "0|CheckMacValue Error";
        } else {
            if ("1".equals(params.get("RtnCode"))
                    && "0".equals(params.get("SimulatePaid"))) {
                System.out.println("付款成功: " + params.get("MerchantTradeNo"));
            }
            reply = "1|OK";
        }
        byte[] resp = reply.getBytes(StandardCharsets.UTF_8);
        ex.sendResponseHeaders(200, resp.length);
        try (OutputStream os = ex.getResponseBody()) { os.write(resp); }
    }

    public static void main(String[] args) throws Exception {
        HttpServer server = HttpServer.create(new InetSocketAddress(3000), 0);
        server.createContext("/checkout", ex -> {
            try { checkoutHandler(ex); } catch (Exception e) {
                e.printStackTrace();
                ex.sendResponseHeaders(500, 0); ex.getResponseBody().close();
            }
        });
        server.createContext("/ecpay/notify", ex -> {
            try { notifyHandler(ex); } catch (Exception e) {
                e.printStackTrace();
                ex.sendResponseHeaders(500, 0); ex.getResponseBody().close();
            }
        });
        server.setExecutor(null);
        server.start();
        System.out.println("Server: http://localhost:3000/checkout");
    }
}
```

**執行**：

```bash
javac EcpayAioDemo.java
java EcpayAioDemo
```

瀏覽 `http://localhost:3000/checkout`。測試信用卡 `4311-9522-2222-2222`，CVV `222`，3D 驗證碼 `1234`。

### AES-JSON 差異指南（基於 Go 參考實作）

> AI 助手：**加密函式必須取自 [guides/14 §Java](./14-aes-encryption.md)**，不可從 Go 直譯。業務流程參考 Go E2E + 以下差異。

#### 環境與依賴

- **最低版本**：JDK 11+（java.net.http.HttpClient 內建）
- **零外部依賴**：純 JDK 實作（HttpServer + HttpClient + MessageDigest）
- 安裝：無需額外安裝（JSON 處理需 Gson）

#### 與 Go 的關鍵差異

| 項目 | Go | Java |
|------|-----|------|
| HTTP Server | net/http | com.sun.net.httpserver.HttpServer |
| HTTP Client | http.Post | java.net.http.HttpClient |
| URL Encode | url.QueryEscape | URLEncoder.encode(s, StandardCharsets.UTF_8) |
| SHA256 | crypto/sha256 | MessageDigest.getInstance("SHA-256") |
| AES | crypto/aes + cipher | javax.crypto.Cipher + SecretKeySpec |
| JSON | encoding/json | Gson（需 `disableHtmlEscaping` + `LinkedHashMap` 保序） |
| Form Parse | r.ParseForm() | 手動解析 InputStream |

#### Java 特有注意事項

1. **URLEncoder 差異**：`URLEncoder.encode()` 將空格編碼為 `+`，波浪號 `~` 編碼為 `%7E`（與 PHP urlencode 一致），但 **星號 `*` 不編碼**（JVM URLEncoder safe set 包含 `*`）— **AES URL encode 必須手動替換 `*→%2A`**；CMV 不受影響（PHP 的 .NET replacement `%2a→*` 使最終結果一致）；完整實作見 [guides/14 §Java](./14-aes-encryption.md)
2. **JSON key 順序**：`HashMap` 不保證順序 — AES-JSON 必須用 `LinkedHashMap`
3. **Gson HTML 轉義**：預設會轉義 `<>&` — 必須 `new GsonBuilder().disableHtmlEscaping().create()`
4. **Hex 格式化**：`String.format("%02x", b)` 產生小寫 hex，CMV 不影響（有 toLowerCase），AES 需確認大寫
5. **CheckMacValue**：完整實作見 [guides/13 §Java](./13-checkmacvalue.md)
6. **AES 加解密**：完整實作見 [guides/14 §Java](./14-aes-encryption.md)

#### 執行方式

需要 Gson：下載 `gson-2.11.0.jar` 或用 Maven/Gradle

```bash
# 編譯與執行
javac -cp gson-2.11.0.jar EcpayDemo.java
java -cp .:gson-2.11.0.jar EcpayDemo
```

---

## C# 完整整合範例 + 差異指南

### CMV-SHA256 — AIO 信用卡付款（完整 Web Server）

> 對應 PHP 範例：`scripts/SDK_PHP/example/Payment/Aio/CreateCreditOrder.php`
> 加密函式取自 [guides/13 §C#](./13-checkmacvalue.md)，不可從 Go 直譯。

> ⚠️ **ECPG 雙 Domain**：Token/建立交易 API 走 `ecpg(-stage).ecpay.com.tw`，查詢/退款走 `ecpayment(-stage).ecpay.com.tw`，混用導致 404。詳見 [guides/02](./02-payment-ecpg.md)。

```csharp
// Program.cs — .NET 6+ Minimal API, 零外部依賴
using System.Net;
using System.Security.Cryptography;
using System.Text;

// 測試用：正式環境改用 Environment.GetEnvironmentVariable("ECPAY_MERCHANT_ID") 等
const string MerchantId = "3002607";
const string HashKey    = "pwFHCqoQZGmho4w6";
const string HashIV     = "EkRm7iFT261dpevs";
const string AioUrl     = "https://payment-stage.ecpay.com.tw/Cashier/AioCheckOut/V5";

// ECPay 專用 URL encode（完整實作見 guides/13 §C#）
// (1) WebUtility.UrlEncode (2) ToLower (3) 還原 .NET 保留字元
static string EcpayUrlEncode(string s)
{
    var encoded = WebUtility.UrlEncode(s)!.Replace("%20", "+");
    encoded = encoded.ToLower();
    return encoded
        .Replace("%2d", "-").Replace("%5f", "_").Replace("%2e", ".")
        .Replace("%21", "!").Replace("%2a", "*")
        .Replace("%28", "(").Replace("%29", ")")
        .Replace("~", "%7e");
}

// 產生 SHA256 CheckMacValue
static string GenerateCheckMacValue(Dictionary<string, string> parameters,
                                    string hashKey, string hashIV)
{
    var filtered = parameters
        .Where(p => p.Key != "CheckMacValue")
        .OrderBy(p => p.Key, StringComparer.OrdinalIgnoreCase)
        .ToList();
    var paramStr = string.Join("&", filtered.Select(p => $"{p.Key}={p.Value}"));
    var raw = $"HashKey={hashKey}&{paramStr}&HashIV={hashIV}";
    var encoded = EcpayUrlEncode(raw);
    var hash = SHA256.HashData(Encoding.UTF8.GetBytes(encoded));
    return BitConverter.ToString(hash).Replace("-", "").ToUpper();
}

var builder = WebApplication.CreateBuilder(args);
builder.WebHost.UseUrls("http://localhost:3000");
var app = builder.Build();

// GET /checkout — 產生自動提交表單，導向 ECPay 付款頁面
app.MapGet("/checkout", () =>
{
    var tradeNo = "ecpay" + DateTimeOffset.UtcNow.ToUnixTimeSeconds();
    var tradeDate = TimeZoneInfo.ConvertTimeFromUtc(DateTime.UtcNow,
        TimeZoneInfo.FindSystemTimeZoneById("Asia/Taipei"))
        .ToString("yyyy/MM/dd HH:mm:ss");

    var formParams = new Dictionary<string, string>
    {
        ["MerchantID"]        = MerchantId,
        ["MerchantTradeNo"]   = tradeNo,
        ["MerchantTradeDate"] = tradeDate,
        ["PaymentType"]       = "aio",
        ["TotalAmount"]       = "100",
        ["TradeDesc"]         = "test",
        ["ItemName"]          = "TestItem",
        ["ReturnURL"]         = "http://localhost:3000/ecpay/notify",
        ["ChoosePayment"]     = "Credit",
        ["EncryptType"]       = "1"
    };
    formParams["CheckMacValue"] = GenerateCheckMacValue(formParams, HashKey, HashIV);

    // 產生自動提交 HTML 表單
    // ⚠️ 正式環境需對 value 進行 WebUtility.HtmlEncode 防止 XSS
    var fields = string.Join("",
        formParams.Select(p =>
            $"<input type=\"hidden\" name=\"{p.Key}\" value=\"{p.Value}\">"));
    var html = $"<form id=\"ecpay\" method=\"POST\" action=\"{AioUrl}\">{fields}</form>" +
               "<script>document.getElementById('ecpay').submit();</script>";
    return Results.Content(html, "text/html; charset=utf-8");
});

// POST /ecpay/notify — 接收 ECPay 付款結果通知
app.MapPost("/ecpay/notify", async (HttpRequest request) =>
{
    var form = await request.ReadFormAsync();
    var formParams = form.ToDictionary(f => f.Key, f => f.Value.ToString());
    formParams.TryGetValue("CheckMacValue", out var received);
    var calculated = GenerateCheckMacValue(formParams, HashKey, HashIV);

    // timing-safe 比對
    var valid = CryptographicOperations.FixedTimeEquals(
        Encoding.UTF8.GetBytes(received ?? ""),
        Encoding.UTF8.GetBytes(calculated));

    if (!valid) return Results.Text("0|CheckMacValue Error");

    if (formParams.GetValueOrDefault("RtnCode") == "1"
        && formParams.GetValueOrDefault("SimulatePaid") == "0")
    {
        Console.WriteLine($"付款成功: {formParams.GetValueOrDefault("MerchantTradeNo")}");
    }
    return Results.Text("1|OK");
});

app.Run();
```

**執行**：

```bash
dotnet new web -n EcpayAioDemo
# 將上方程式碼貼入 Program.cs
dotnet run
```

瀏覽 `http://localhost:3000/checkout`。測試信用卡 `4311-9522-2222-2222`，CVV `222`，3D 驗證碼 `1234`。

### AES-JSON 差異指南（基於 Go 參考實作）

> AI 助手：**加密函式必須取自 [guides/14 §C#](./14-aes-encryption.md)**，不可從 Go 直譯。業務流程參考 Go E2E + 以下差異。

#### 環境與依賴

- **最低版本**：.NET 6+（HttpClient、System.Text.Json 內建）
- **零外部依賴**：純 .NET 實作（ASP.NET Core Minimal API）
- 安裝：`dotnet new web -n EcpayDemo`

#### 與 Go 的關鍵差異

| 項目 | Go | C# |
|------|-----|------|
| HTTP Server | net/http | ASP.NET Core Minimal API (`app.MapPost`) |
| HTTP Client | http.Post | HttpClient + FormUrlEncodedContent |
| URL Encode | url.QueryEscape | WebUtility.UrlEncode（注意：不同於 HttpUtility.UrlEncode） |
| SHA256 | crypto/sha256 | System.Security.Cryptography.SHA256 |
| AES | crypto/aes + cipher | System.Security.Cryptography.Aes |
| JSON | encoding/json | System.Text.Json.JsonSerializer |
| Form Parse | r.ParseForm() | `await Request.ReadFormAsync()` |

#### C# 特有注意事項

1. **WebUtility vs HttpUtility**：`WebUtility.UrlEncode` 將空格編碼為 `+`，波浪號 `~` 不編碼 — 需手動替換 `~→%7E`。不要用 `HttpUtility.UrlEncode`（行為不同）
2. **JSON key 順序**：class 屬性定義順序即為 JSON key 順序 — 不需額外設定
3. **HTML 轉義問題**：`System.Text.Json` **預設轉義** `<>&+'` — 必須設定 `JsonSerializerOptions { Encoder = JavaScriptEncoder.UnsafeRelaxedJsonEscaping }` 才能輸出正確 JSON（同 line 103 對照表所示）
4. **AES Padding**：.NET 的 `Aes.Create()` 預設使用 PKCS7 padding（與 ECPay 需求一致）
5. **CheckMacValue**：完整實作見 [guides/13 §C#](./13-checkmacvalue.md)
6. **AES 加解密**：完整實作見 [guides/14 §C#](./14-aes-encryption.md)

#### 執行方式

```bash
dotnet run
```

> **C# HttpClient 注意**：`HttpClient` 應宣告為 `static readonly` 或透過 `IHttpClientFactory`（.NET 5+）注入，避免重複建立導致 socket exhaustion。

---

## TypeScript 整合指引

### TypeScript 完整 CMV-SHA256 Callback 伺服器

> 完整可執行範例（JDK 21 → Node 20 + TypeScript 5.x，含型別安全 Callback 驗證）
> 加密函式取自 [guides/13 §TypeScript](./13-checkmacvalue.md)，請勿從 Node.js 直接複製。

```bash
npm init -y
npm install express
npm install -D typescript @types/express @types/node ts-node
npx tsc --init --strict --target ES2020 --module commonjs --esModuleInterop
```

```typescript
// ecpay-aio.ts — TypeScript 5.x, Node 20+, zero non-dev dependencies
import * as crypto from 'crypto';
import * as http from 'http';
import * as https from 'https';
import * as querystring from 'querystring';

// ─────────────────── 型別定義 ───────────────────
interface EcpayConfig {
  merchantId: string;
  hashKey: string;
  hashIv: string;
  aioUrl: string;
}

interface AioOrderParams {
  MerchantID: string;
  MerchantTradeNo: string;
  MerchantTradeDate: string;
  PaymentType: 'aio';
  TotalAmount: string;
  TradeDesc: string;
  ItemName: string;
  ReturnURL: string;
  ChoosePayment: 'Credit' | 'ATM' | 'CVS' | 'BARCODE' | 'ALL';
  EncryptType: '1';
  SimulatePaid?: '0' | '1';
  [key: string]: string | undefined;
}

interface AioCallbackParams {
  MerchantID: string;
  MerchantTradeNo: string;
  RtnCode: string;      // ⚠️ AIO CMV 協議：字串 '1'（非整數）
  RtnMsg: string;
  TradeNo: string;
  TradeAmt: string;
  PaymentDate: string;
  PaymentType: string;
  CheckMacValue: string;
  SimulatePaid: string;
  [key: string]: string;
}

// ─────────────────── 設定 ───────────────────
const config: EcpayConfig = {
  merchantId: process.env.ECPAY_MERCHANT_ID ?? '3002607',
  hashKey:    process.env.ECPAY_HASH_KEY    ?? 'pwFHCqoQZGmho4w6',
  hashIv:     process.env.ECPAY_HASH_IV     ?? 'EkRm7iFT261dpevs',
  aioUrl:     'https://payment-stage.ecpay.com.tw/Cashier/AioCheckOut/V5',
};

// ─────────────────── CheckMacValue（取自 guides/13 §TypeScript）───────────────────
function ecpayUrlEncode(s: string): string {
  return encodeURIComponent(s)
    .replace(/%20/g, '+')
    .replace(/!/g,   '%21')
    .replace(/'/g,   '%27')
    .replace(/\(/g,  '%28')
    .replace(/\)/g,  '%29')
    .replace(/\*/g,  '%2A')
    .replace(/~/g,   '%7e')
    .toLowerCase()
    .replace(/%2d/g, '-')
    .replace(/%5f/g, '_')
    .replace(/%2e/g, '.');
}

function generateCheckMacValue(
  params: Record<string, string>,
  hashKey: string,
  hashIv: string
): string {
  const filtered = Object.entries(params)
    .filter(([k]) => k !== 'CheckMacValue')
    .sort(([a], [b]) => a.toLowerCase().localeCompare(b.toLowerCase()));

  const raw = `HashKey=${hashKey}&${filtered.map(([k, v]) => `${k}=${v}`).join('&')}&HashIV=${hashIv}`;
  const encoded = ecpayUrlEncode(raw);
  return crypto.createHash('sha256').update(encoded, 'utf8').digest('hex').toUpperCase();
}

/** timing-safe 比對（見 guides/13 §timing-safe）*/
function timingSafeEqual(a: string, b: string): boolean {
  const bufA = Buffer.from(a);
  const bufB = Buffer.from(b);
  if (bufA.length !== bufB.length) return false;
  return crypto.timingSafeEqual(bufA, bufB);
}

// ─────────────────── 工具函式 ───────────────────
function nowTaipeiFormatted(): string {
  return new Date().toLocaleString('zh-TW', {
    timeZone: 'Asia/Taipei',
    year: 'numeric', month: '2-digit', day: '2-digit',
    hour: '2-digit', minute: '2-digit', second: '2-digit',
    hour12: false,
  }).replace(/\//g, '/');
}

function parseFormBody(body: string): Record<string, string> {
  return querystring.parse(body) as Record<string, string>;
}

// ─────────────────── HTTP 伺服器 ───────────────────
const server = http.createServer((req, res) => {
  const url = req.url ?? '/';

  if (url === '/checkout' && req.method === 'GET') {
    // GET /checkout — 建立 AIO 訂單，回傳自動提交表單
    const params: AioOrderParams = {
      MerchantID:        config.merchantId,
      MerchantTradeNo:   'ecpay' + Date.now().toString().slice(0, 10),
      MerchantTradeDate: nowTaipeiFormatted(),
      PaymentType:       'aio',
      TotalAmount:       '100',
      TradeDesc:         'Test',
      ItemName:          'TestItem',
      ReturnURL:         'https://your-server.example.com/ecpay/notify', // ⚠️ TODO: 替換
      ChoosePayment:     'Credit',
      EncryptType:       '1',
      SimulatePaid:      '1',  // ← [正式時移除]
    };

    const cleanParams: Record<string, string> = Object.fromEntries(
      Object.entries(params).filter(([, v]) => v !== undefined) as [string, string][]
    );
    cleanParams.CheckMacValue = generateCheckMacValue(cleanParams, config.hashKey, config.hashIv);

    const fields = Object.entries(cleanParams)
      .map(([k, v]) => `<input type="hidden" name="${k}" value="${v}">`)
      .join('');

    res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
    res.end(`<form id="f" method="POST" action="${config.aioUrl}">${fields}</form>
             <script>document.getElementById('f').submit()</script>`);

  } else if (url === '/ecpay/notify' && req.method === 'POST') {
    // POST /ecpay/notify — 接收付款結果 Callback（Server-to-Server Form POST）
    let body = '';
    req.on('data', (chunk: Buffer) => { body += chunk.toString(); });
    req.on('end', () => {
      const params = parseFormBody(body) as AioCallbackParams;

      // ① timing-safe 驗簽（禁止用 === 或 ==）
      const received = params.CheckMacValue ?? '';
      const computed = generateCheckMacValue(params, config.hashKey, config.hashIv);
      if (!timingSafeEqual(computed, received)) {
        console.error('ECPay callback CheckMacValue 驗證失敗');
        res.writeHead(200, { 'Content-Type': 'text/plain' });
        res.end('1|OK');  // 仍需回 1|OK，避免 ECPay 重試
        return;
      }

      // ② RtnCode 型別：AIO CMV 協議為字串 '1'（非整數 1）
      if (params.RtnCode === '1' && params.SimulatePaid !== '1') {
        // 付款成功，更新訂單狀態
        console.log('✅ 付款成功', params.MerchantTradeNo, params.TradeNo);
      }

      res.writeHead(200, { 'Content-Type': 'text/plain' });
      res.end('1|OK');  // ⚠️ 精確 ASCII，無引號、無換行
    });

  } else {
    res.writeHead(404);
    res.end('Not Found');
  }
});

server.listen(3000, () => {
  console.log('TypeScript ECPay Server: http://localhost:3000/checkout');
});
```

> **成功標誌**：瀏覽 `http://localhost:3000/checkout` 應看到頁面自動跳轉至綠界付款頁（Content-Type: text/html）。
> **驗證加密**：執行 `python test-vectors/verify.py` 確認 CheckMacValue 實作正確。

---

> TypeScript 的 ECPay 整合程式碼與 Node.js **幾乎完全相同**（使用相同的 `crypto`、`Buffer`、`encodeURIComponent` 模組），主要差異僅在型別標注。

**建議做法**：直接使用 Node.js E2E 範例（[guides/00-getting-started.md](./00-getting-started.md) §Quick Start + §AES-JSON 端到端範例），加上 TypeScript 型別標注即可。

**TypeScript 專屬設定**：

```bash
# 專案初始化
npm init -y && npm install express && npm install -D typescript @types/express @types/node ts-node
npx tsc --init --strict --target ES2020 --module commonjs --esModuleInterop
```
**關鍵型別定義**：

```typescript
interface EcpayParams { [key: string]: string; }
interface AesRequest { MerchantID: string; RqHeader: { Timestamp: number }; Data: string; }
interface AesResponse { TransCode: number; TransMsg: string; Data: string; }

// 回呼參數型別（AIO CMV-SHA256）
interface AioCallbackParams {
  MerchantID: string;
  MerchantTradeNo: string;
  RtnCode: string;
  RtnMsg: string;
  TradeNo: string;
  TradeAmt: string;
  PaymentDate: string;
  PaymentType: string;
  CheckMacValue: string;
  SimulatePaid: string;
  [key: string]: string;
}
```
**加密函式**：見 [guides/13 §TypeScript](./13-checkmacvalue.md)（CMV）及 [guides/14 §TypeScript](./14-aes-encryption.md)（AES）。

> AI 生成 TypeScript E2E 時：以 Node.js 範例為基底，加上上述型別，其餘邏輯完全相同。

**tsconfig.json 關鍵設定**：

```json
{
  "compilerOptions": {
    "strict": true,
    "target": "ES2020",
    "module": "commonjs",
    "esModuleInterop": true,
    "resolveJsonModule": true,
    "skipLibCheck": true
  }
}
```

> **Webhook 型別安全提示**：使用上方 AioCallbackParams 型別搭配 Express Request 可確保回呼參數的型別安全：
> const params = req.body as AioCallbackParams;

---


## Kotlin 整合差異指南（基於 Go 參考實作）

> 完整 E2E 結構同 Go 範例。以下僅列出 Kotlin 與 Go 的關鍵差異。
> AI 助手：**加密函式必須取自 [guides/13 §Kotlin](./13-checkmacvalue.md) 和 [guides/14 §Kotlin](./14-aes-encryption.md)**，不可從 Go 直譯。業務流程參考 Go E2E + 以下差異。

> ⚠️ **ECPG 雙 Domain**：Token/建立交易 API 走 `ecpg(-stage).ecpay.com.tw`，查詢/退款走 `ecpayment(-stage).ecpay.com.tw`，混用導致 404。詳見 [guides/02](./02-payment-ecpg.md)。

### 環境與依賴

- **最低版本**：Kotlin/JVM 1.8+（JDK 11+）
- **推薦依賴**：Gson（JSON）；HTTP Client 使用 JDK 內建 `java.net.http.HttpClient`（無需額外依賴）
- 安裝：`implementation("com.google.code.gson:gson:2.10")`

### 與 Go 的關鍵差異

| 項目 | Go | Kotlin |
|------|-----|------|
| HTTP Server | net/http | com.sun.net.httpserver.HttpServer（同 Java） |
| HTTP Client | http.Post | `java.net.http.HttpClient`（JDK 內建，無需額外依賴）|
| URL Encode | url.QueryEscape | URLEncoder.encode(s, "UTF-8") |
| SHA256 | crypto/sha256 | MessageDigest.getInstance("SHA-256")（同 Java） |
| AES | crypto/aes + cipher | javax.crypto.Cipher（同 Java） |
| JSON | encoding/json | Gson（同 Java 問題：需 `disableHtmlEscaping` + `linkedMapOf` 保序） |
| Form Parse | r.ParseForm() | 手動解析 InputStream |

### Kotlin 特有注意事項

1. **linkedMapOf()**：Kotlin 的 `linkedMapOf()` 等同 Java 的 `LinkedHashMap` — AES-JSON 必須用此保證 key 順序
2. **URLEncoder 差異**：`URLEncoder.encode(s, "UTF-8")` 將 `~` 編碼為 `%7E`（與 PHP urlencode 一致），但 **`*` 不編碼**（同 Java）— AES URL encode 需手動補 `*→%2A`；完整實作見 [guides/14 §Kotlin](./14-aes-encryption.md)
3. **Gson 問題同 Java**：必須 `GsonBuilder().disableHtmlEscaping().create()`
4. **Extension Functions**：可用擴充函式封裝 `String.urlEncode()`、`String.sha256()` 等，程式碼更簡潔
5. **Coroutines**：若用 ktor 替代 java.net.http.HttpClient，需注意 suspend 函式的錯誤處理
6. **CheckMacValue**：完整實作見 [guides/13 §Kotlin](./13-checkmacvalue.md)
7. **AES 加解密**：完整實作見 [guides/14 §Kotlin](./14-aes-encryption.md)

### 執行方式

```bash
# 編譯與執行
kotlinc -include-runtime -cp gson-2.10.0.jar -d ecpay.jar EcpayDemo.kt
java -cp ecpay.jar:gson-2.10.0.jar EcpayDemoKt
```

---

## Ruby 整合差異指南（基於 Go 參考實作）

> 完整 E2E 結構同 Go 範例。以下僅列出 Ruby 與 Go 的關鍵差異。
> AI 助手：**加密函式必須取自 [guides/13 §Ruby](./13-checkmacvalue.md) 和 [guides/14 §Ruby](./14-aes-encryption.md)**，不可從 Go 直譯。業務流程參考 Go E2E + 以下差異。

> ⚠️ **ECPG 雙 Domain**：Token/建立交易 API 走 `ecpg(-stage).ecpay.com.tw`，查詢/退款走 `ecpayment(-stage).ecpay.com.tw`，混用導致 404。詳見 [guides/02](./02-payment-ecpg.md)。

### 環境與依賴

- **最低版本**：Ruby 3.1+
- **Web Server**：Sinatra（輕量）或 WEBrick（標準庫）
- 安裝：`gem install sinatra`（或零依賴使用 WEBrick）

### 與 Go 的關鍵差異

| 項目 | Go | Ruby |
|------|-----|------|
| HTTP Server | net/http | Sinatra (`post '/ecpay'`) 或 WEBrick |
| HTTP Client | http.Post | Net::HTTP.post_form |
| URL Encode | url.QueryEscape | CGI.escape（注意：空格編碼為 `+`） |
| SHA256 | crypto/sha256 | Digest::SHA256.hexdigest |
| AES | crypto/aes + cipher | OpenSSL::Cipher::AES |
| JSON | encoding/json | JSON.generate（Hash 依插入順序，Ruby 1.9+） |
| Form Parse | r.ParseForm() | `params[:key]`（Sinatra 自動解析） |

### Ruby 特有注意事項

1. **CGI.escape vs ERB::Util.url_encode**：`CGI.escape` 將空格編碼為 `+`（符合 ECPay 需求），波浪號 `~` 需手動替換為 `%7E`
2. **Hash 順序**：Ruby 1.9+ Hash 保持插入順序 — AES-JSON 不需特殊處理
3. **JSON 格式**：用 `JSON.generate(data)` 產生 compact JSON，勿用 `JSON.pretty_generate`
4. **OpenSSL padding**：`OpenSSL::Cipher` 預設使用 PKCS7 padding（與 ECPay 需求一致）
5. **CheckMacValue**：完整實作見 [guides/13 §Ruby](./13-checkmacvalue.md)
6. **AES 加解密**：完整實作見 [guides/14 §Ruby](./14-aes-encryption.md)

### 執行方式

```bash
gem install sinatra
ruby ecpay_demo.rb
```

---

## Swift 整合差異指南（基於 Go 參考實作）

> 完整 E2E 結構同 Go 範例。以下僅列出 Swift 與 Go 的關鍵差異。
> AI 助手：**加密函式必須取自 [guides/13 §Swift](./13-checkmacvalue.md) 和 [guides/14 §Swift](./14-aes-encryption.md)**，不可從 Go 直譯。業務流程參考 Go E2E + 以下差異。

> ⚠️ **ECPG 雙 Domain**：Token/建立交易 API 走 `ecpg(-stage).ecpay.com.tw`，查詢/退款走 `ecpayment(-stage).ecpay.com.tw`，混用導致 404。詳見 [guides/02](./02-payment-ecpg.md)。

### 環境與依賴

- **最低版本**：Swift 5.7+（iOS 13+ / macOS 10.15+）
- **零外部依賴**：Foundation URLSession + CommonCrypto
- CLI 範例可直接 `swift run`，iOS 需 Xcode 專案

### 與 Go 的關鍵差異

| 項目 | Go | Swift |
|------|-----|------|
| HTTP Server | net/http | 無內建（CLI 用 swift-nio 或 Vapor；iOS 不需 server） |
| HTTP Client | http.Post | URLSession.shared.dataTask / async-await |
| URL Encode | url.QueryEscape | `addingPercentEncoding(withAllowedCharacters:)` + 手動替換 |
| SHA256 | crypto/sha256 | CommonCrypto `CC_SHA256` 或 CryptoKit（iOS 13+） |
| AES | crypto/aes + cipher | CommonCrypto `CCCrypt` |
| JSON | encoding/json | JSONEncoder（需 `.sortedKeys`）或 Codable struct |
| Form Parse | r.ParseForm() | URLComponents 解析 query string |

### Swift 特有注意事項

1. **URL Encode 複雜**：Swift 無直接等同 PHP `urlencode` 的函式 — 需 `addingPercentEncoding` + 手動替換 `%20→+`（空格）、`*→%2A`、`~→%7E`、`+→%2B`；完整實作見 [guides/13 §Swift](./13-checkmacvalue.md) 和 [guides/14 §Swift](./14-aes-encryption.md)
2. **JSONEncoder 排序**：預設不保證 key 順序 — AES-JSON 必須設定 `.sortedKeys` 或用 Codable struct 定義欄位順序
3. **CommonCrypto**：需 `import CommonCrypto`，C 函式風格（CCCrypt），或用 CryptoKit（更 Swift 風格）
4. **iOS 付款**：App 內付款請用 SFSafariViewController（見下方 Mobile App 區段），不要在 App 內實作完整 AIO flow
5. **CheckMacValue**：完整實作見 [guides/13 §Swift](./13-checkmacvalue.md)
6. **AES 加解密**：完整實作見 [guides/14 §Swift](./14-aes-encryption.md)

### 執行方式

```bash
swift ecpay_demo.swift
```

---

## Rust 整合差異指南（基於 Go 參考實作）

> 完整 E2E 結構同 Go 範例。以下僅列出 Rust 與 Go 的關鍵差異。
> AI 助手：**加密函式必須取自 [guides/13 §Rust](./13-checkmacvalue.md) 和 [guides/14 §Rust](./14-aes-encryption.md)**，不可從 Go 直譯。業務流程參考 Go E2E + 以下差異。

> ⚠️ **ECPG 雙 Domain**：Token/建立交易 API 走 `ecpg(-stage).ecpay.com.tw`，查詢/退款走 `ecpayment(-stage).ecpay.com.tw`，混用導致 404。詳見 [guides/02](./02-payment-ecpg.md)。

### 環境與依賴

- **推薦框架**：axum（Web Server）+ reqwest（HTTP Client）
- **Cargo 依賴**：`axum`, `reqwest`, `serde_json`, `sha2`, `aes`, `cbc`, `hex`, `form_urlencoded`
- 安裝：`cargo add axum reqwest serde serde_json sha2 aes cbc hex form_urlencoded tokio --features tokio/full`

### 與 Go 的關鍵差異

| 項目 | Go | Rust |
|------|-----|------|
| HTTP Server | net/http | axum (`Router::new().route(...)`) |
| HTTP Client | http.Post | reqwest::Client::new().post(...) |
| URL Encode | url.QueryEscape | form_urlencoded::byte_serialize + 手動替換 |
| SHA256 | crypto/sha256 | sha2::Sha256 (`Digest` trait) |
| AES | crypto/aes + cipher | aes + cbc crates（`Encryptor`/`Decryptor`） |
| JSON | encoding/json | serde_json（struct 欄位定義順序） |
| Form Parse | r.ParseForm() | axum `Form<HashMap<String, String>>` extractor |

### Rust 特有注意事項

1. **URL Encode**：`form_urlencoded::byte_serialize` 符合 WHATWG 規範，將空格編碼為 `+`，且自動編碼 `~`→`%7E`、`!`、`'`、`(`、`)` 等。⚠️ **例外**：星號 `*`（0x2A）在 WHATWG safe set 中**不會被編碼**，但 PHP `urlencode("*")` 輸出 `%2A` — **AES URL encode 必須手動補 `.replace("*", "%2A")`**（CMV 不影響：PHP 的 .NET replacement `%2a→*` 使最終結果一致）；完整實作見 [guides/14 §Rust](./14-aes-encryption.md)
2. **Hex 大寫**：AES URL encode 必須使用大寫 hex（`%7E`、`%2A`）— 確認 hex encode 輸出格式，詳見 [guides/14 §Rust](./14-aes-encryption.md)
3. **JSON key 順序**：用 `#[derive(Serialize)]` struct 確保欄位順序穩定；`serde_json::Map` 使用 BTreeMap（字母序）
4. **所有權與生命週期**：加密函式通常接受 `&str` 並回傳 `String`，避免不必要的 clone
5. **async runtime**：axum + reqwest 皆需 tokio runtime（`#[tokio::main]`）
6. **CheckMacValue**：完整實作見 [guides/13 §Rust](./13-checkmacvalue.md)
7. **AES 加解密**：完整實作見 [guides/14 §Rust](./14-aes-encryption.md)

### 執行方式

```bash
cargo run
```

---

## Mobile App 付款整合指引

ECPay 不提供原生 iOS/Android SDK（站內付 2.0 App SDK 除外），App 內付款需透過 WebView 載入 ECPay 付款頁面。

### iOS (Swift/Objective-C)

| 方案 | 推薦度 | 說明 |
|------|--------|------|
| **SFSafariViewController** | ⭐⭐⭐ 推薦 | 獨立 cookie 沙箱、系統級安全、支援自動填入 |
| WKWebView | ⭐⭐ | 可自訂 UI，但需處理 cookie 和 JS 安全問題 |
| 外部瀏覽器 | ⭐ | 最簡單但使用者體驗差（跳出 App） |

**ReturnURL 處理**：
1. 設定 Universal Links（Apple Developer Console + apple-app-site-association）
2. 在 `SceneDelegate.scene(_:continue:)` 中接收回呼
3. 解析 URL 參數，更新 App 內訂單狀態

**注意**：ReturnURL 是前端頁面跳轉，**不可**作為付款成功判斷依據。必須搭配 server-side 的 callback（ReturnURL server-to-server）確認付款狀態。

### Android (Kotlin/Java)

| 方案 | 推薦度 | 說明 |
|------|--------|------|
| **Custom Tabs (Chrome)** | ⭐⭐⭐ 推薦 | 系統瀏覽器核心、最佳效能、支援自動填入 |
| WebView | ⭐⭐ | 可自訂 UI，但需處理 cookie 和安全性 |
| 外部瀏覽器 | ⭐ | 最簡單但使用者體驗差 |

**ReturnURL 處理**：
1. 在 AndroidManifest.xml 設定 Deep Link intent-filter
2. 在 Activity 的 `onNewIntent()` 中接收回呼
3. 解析 Intent data，更新 App 內訂單狀態

### 站內付 2.0 App SDK

如需更深度的 App 整合（例如 App 內信用卡表單），可考慮站內付 2.0 App SDK。詳見 [guides/02-payment-ecpg.md](./02-payment-ecpg.md) 的 App 整合段落。

#### Mobile App 實戰指引

> **iOS（Swift）關鍵步驟**：
> 1. 後端呼叫 GetTokenbyTrade 取得 Token → 回傳給 App
> 2. App 使用 ECPay JavaScript SDK 於 WKWebView 中載入付款頁面
> 3. WKWebView 攔截 `decidePolicyFor navigationAction` 處理 ThreeDURL 導向
> 4. Callback 由後端 ReturnURL 接收（非 App 端處理）
> 5. App 透過輪詢後端 API 或 WebSocket 取得付款結果
>
> **Android（Kotlin）關鍵步驟**：
> 1. 後端呼叫 GetTokenbyTrade 取得 Token → 回傳給 App
> 2. App 使用 WebView 載入 ECPay JavaScript SDK 付款頁面
> 3. WebView 設定 `WebViewClient.shouldOverrideUrlLoading` 處理 ThreeDURL
> 4. Callback 由後端 ReturnURL 接收（非 App 端處理）
> 5. App 透過輪詢後端 API 或 Push Notification 取得付款結果
>
> ⚠️ **禁止在 App 端儲存 HashKey/HashIV**：所有加密運算必須在後端完成。App 只負責前端顯示和使用者互動。
> 詳細的 App 整合指南見 [guides/02c](./02c-ecpg-app-production.md)。

---

## 非 PHP 信用卡付款統一 Checklist

以下 9 步驟適用於任何語言的 AIO 信用卡付款整合，按順序完成即可：

1. **實作 `ecpayUrlEncode`** — 對照 [guides/13](./13-checkmacvalue.md) 的各語言 URL Encode 行為差異表
2. **實作 `generateCheckMacValue`** — SHA256 版本，對照 guides/13 的完整流程
3. **驗證測試向量** — 用 guides/13 的 SHA256 測試向量確認結果為 `291CBA...57FB2`
4. **建立 checkout 端點** — 組裝參數 + 生成 CMV + 輸出自動提交的 HTML form
5. **建立 notify 端點** — 接收 ECPay POST callback，解析 form 參數
6. **驗證 CMV** — 用 timing-safe 比較驗證回呼的 CheckMacValue
7. **回應 `1|OK`** — 驗證通過後**必須**回應純文字 `1|OK`（無 HTML、無 BOM）
8. **ngrok 測試** — 用 `ngrok http 3000` 產生公開 URL，設為 ReturnURL 進行端對端測試
9. **切正式環境** — 替換 MerchantID/HashKey/HashIV + URL 去掉 `-stage` → 完成

> **常見錯誤**：
> - 忘記 `EncryptType=1`（必須設為 1 表示 SHA256）
> - ReturnURL 回應了 HTML 而非純文字 `1|OK`
> - 未驗證 `SimulatePaid` 欄位（測試環境預設為模擬付款）
> - 回呼處理拋出例外導致未回應 `1|OK`（ECPay 會持續重送）

## 非 PHP AES-JSON 統一 Checklist（ECPG / 發票 / 全方位物流 / ECTicket）

以下 10 步驟適用於任何語言的 AES-JSON 協議整合：

1. **實作 AES-128-CBC 加密** — 對照 [guides/14](./14-aes-encryption.md) 的各語言實作
2. **實作 AES-128-CBC 解密** — 同上，注意 PKCS7 unpadding
3. **驗證 AES 測試向量** — 用 guides/14 的測試向量確認加解密結果正確
4. **實作 `aesUrlEncode`** — 對照 guides/14 §AES URL Encode 各語言差異表（注意與 CMV 的 `ecpayUrlEncode` 邏輯不同）
5. **組裝三層 JSON 請求** — `{ MerchantID, RqHeader: { Timestamp }, Data: "加密字串" }`
6. **Data 加密流程** — 業務 JSON → URL encode → AES 加密 → Base64
7. **解析三層 JSON 回應** — 先檢查 `TransCode`，再解密 `Data`，最後檢查 `RtnCode`
8. **處理 Callback** — 接收 POST JSON → 解密 Data → 驗證 → 回應對應格式（站內付 2.0 / 信用卡幕後授權回 `1|OK`，全方位/跨境物流回 AES 加密 JSON）
9. **ngrok 端對端測試** — `ngrok http 3000` 產生公開 URL 進行完整測試
10. **切正式環境** — 替換帳號 + URL 去掉 `-stage`

> **與 CMV-SHA256 Checklist 的差異**：AES-JSON 需做雙層錯誤檢查（TransCode + RtnCode），且 URL encode 邏輯不同（不做 toLowerCase）。

---

## 各語言 E2E 組裝步驟（Delta 指南使用說明）

本指南採用 **Go 完整 E2E** 作為參考實作，其餘語言僅提供差異（delta）部分。組裝步驟：

1. **閱讀 Go E2E 範例**（本文件上方）— 理解完整金流串接流程（建單→加密→送出→回呼驗證→回應）
2. **套用你的語言的 delta 區段** — 將 Go 語法替換為目標語言的等效寫法（HTTP client、JSON 處理、Web framework）
3. **置換加密模組** — 使用 [guides/13](./13-checkmacvalue.md) 和 [guides/14](./14-aes-encryption.md) 中對應語言的加密實作
4. **用測試向量驗證** — 確認 CMV 和 AES 輸出與 guides/13、14 中的測試向量一致

> **提示**：多數語言的 delta 僅涉及 HTTP 框架和 JSON 序列化差異，核心金流邏輯（參數組裝、加密、驗證）在所有語言中完全一致。

---

## C/C++ 整合注意事項

C/C++ 極少用於 Web 整合，本節提供 CMV-SHA256 與 AES-JSON 兩種協議的最小 POST 骨架及必要的依賴資訊。

#### C/C++ HTTP 流程差異指南

> C/C++ 開發者除了 guides/13（CMV）+ guides/14（AES）的加密函式外，還需要自行實作 HTTP 請求。以下為關鍵差異：

| 項目 | 建議方案 | 注意事項 |
|------|---------|---------|
| HTTP Client | libcurl（`curl_easy_perform`） | 設定 `CURLOPT_POST`、`CURLOPT_POSTFIELDS`；JSON POST 需設 `Content-Type: application/json` |
| JSON 解析 | cJSON（C）/ nlohmann/json（C++） | ECPay 回應為 JSON 或 URL-encoded，需根據服務類型切換解析方式 |
| URL Encode | 自訂實作（見 guides/13 §C） | `curl_easy_escape` 行為因版本不同，建議用 guides/13 §C 的統一實作 |
| 記憶體管理 | 所有 `malloc` 回傳值需 `free` | 搭配 Valgrind / AddressSanitizer 測試 |
| TLS | libcurl 內建 OpenSSL 支援 | 確認 CA 憑證路徑正確（`CURLOPT_CAINFO`） |

> **最小可運行流程**（AIO 金流為例）：
> 1. 組裝參數 → `calc_check_mac_value()`（guides/13 §C）
> 2. 組裝 Form POST body（`key1=val1&key2=val2&CheckMacValue=XXX`）
> 3. `curl_easy_setopt(curl, CURLOPT_URL, "https://payment-stage.ecpay.com.tw/Cashier/AioCheckOut/V5")`
> 4. `curl_easy_setopt(curl, CURLOPT_POSTFIELDS, body)`
> 5. 處理 HTML 回應（AIO 回傳 HTML 重導頁面）

### 編譯依賴

| 依賴 | 最低版本 | C 用途 | C++ 用途 |
|------|---------|--------|---------|
| OpenSSL | 3.0+ | AES-128-CBC, SHA256, MD5 | 同左 |
| libcurl | 8.0+ | HTTP POST | 或用 cpr 1.10+ |
| cJSON | 1.7+ | JSON 序列化 | 或用 nlohmann/json 3.11+ |

### CMake 範例

```cmake
cmake_minimum_required(VERSION 3.16)
project(ecpay_demo)

# C 版本
find_package(OpenSSL REQUIRED)
find_package(CURL REQUIRED)
find_package(cJSON REQUIRED)
add_executable(ecpay_c main.c)
target_link_libraries(ecpay_c OpenSSL::SSL OpenSSL::Crypto CURL::libcurl cjson)

# C++ 版本
include(FetchContent)
FetchContent_Declare(cpr GIT_REPOSITORY https://github.com/libcpr/cpr.git GIT_TAG 1.10.5)
FetchContent_Declare(json GIT_REPOSITORY https://github.com/nlohmann/json.git GIT_TAG v3.11.3)
FetchContent_MakeAvailable(cpr json)
add_executable(ecpay_cpp main.cpp)
target_link_libraries(ecpay_cpp cpr::cpr nlohmann_json::nlohmann_json OpenSSL::SSL OpenSSL::Crypto)
```

### AIO CMV-SHA256 最小 POST 骨架（C + libcurl）

> 以下展示如何將 [guides/13 §C](./13-checkmacvalue.md) 的加密函式與 libcurl 組合成完整 AIO API 呼叫。
> `generate_check_mac_value()` 與 `ecpay_url_encode()` 完整實作見 guides/13 §C。

```c
/* ECPay AIO CMV-SHA256 最小 POST 骨架（C + libcurl）
   建置：gcc main.c ecpay_cmv.c -lcurl -lssl -lcrypto -o ecpay_demo
   generate_check_mac_value() 完整實作見 guides/13 §C */

#include <stdio.h>
#include <string.h>
#include <stdlib.h>
#include <time.h>
#include <curl/curl.h>

/* 來自 guides/13 §C 的函式宣告 */
char *ecpay_url_encode(CURL *curl, const char *source);
char *generate_check_mac_value(const char *merchant_id,
    const char *hash_key, const char *hash_iv,
    const char **keys, const char **vals, int n);

int main(void) {
    char trade_no[24];
    snprintf(trade_no, sizeof(trade_no), "C%ld", (long)time(NULL));

    /* 1. 參數（依 ASCII 不分大小寫排序） */
    const char *keys[] = {
        "ChoosePayment", "EncryptType", "ItemName",
        "MerchantID",    "MerchantTradeDate", "MerchantTradeNo",
        "PaymentType",   "ReturnURL", "TotalAmount", "TradeDesc"
    };
    const char *vals[] = {
        "ALL", "1", "測試商品",
        "3002607", "2026/01/01 00:00:00", trade_no,
        "aio", "https://example.com/notify", "100", "test"
        /* ⚠️ 正式環境從環境變數讀取 MerchantID / HashKey / HashIV */
    };
    int n = 10;

    /* 2. 計算 CheckMacValue（guides/13 §C 實作） */
    char *cmv = generate_check_mac_value(
        "3002607", "pwFHCqoQZGmho4w6", "EkRm7iFT261dpevs",
        keys, vals, n);

    /* 3. 組裝 form-urlencoded POST body */
    char body[2048] = "";
    for (int i = 0; i < n; i++) {
        char buf[256];
        snprintf(buf, sizeof(buf), "%s%s=%s", i ? "&" : "", keys[i], vals[i]);
        strncat(body, buf, sizeof(body) - strlen(body) - 1);
    }
    snprintf(body + strlen(body), sizeof(body) - strlen(body),
             "&CheckMacValue=%s", cmv);
    free(cmv);

    /* 4. libcurl POST（ECPay 回傳含自動送出 <form> 的 HTML 付款頁面） */
    CURL *curl = curl_easy_init();
    curl_easy_setopt(curl, CURLOPT_URL,
        "https://payment-stage.ecpay.com.tw/Cashier/AioCheckOut/V5");
    curl_easy_setopt(curl, CURLOPT_POSTFIELDS, body);
    CURLcode rc = curl_easy_perform(curl);
    if (rc != CURLE_OK)
        fprintf(stderr, "curl error: %s\n", curl_easy_strerror(rc));
    curl_easy_cleanup(curl);
    return rc == CURLE_OK ? 0 : 1;
}
```

> **ReturnURL Callback**：ECPay 伺服器交易完成後會 POST 至 `ReturnURL`，你的 C 伺服器（或其他語言）必須回應純字串 `1|OK`（無 HTML、無 BOM）。見 [guides/21 §CMV-SHA256 Callback](./21-webhook-events-reference.md)。

### AES-JSON — B2C 發票開立最小骨架（C + libcurl + cJSON）

> 對應 PHP 範例：`scripts/SDK_PHP/example/Invoice/B2C/Issue.php`
> `ecpay_aes_encrypt()` / `ecpay_aes_decrypt()` 完整實作見 [guides/14 §C](./14-aes-encryption.md)。

```c
/* ECPay AES-JSON B2C 發票開立最小骨架（C + libcurl + cJSON）
   建置：gcc main.c ecpay_aes.c -lcurl -lssl -lcrypto -lcjson -o ecpay_invoice
   ecpay_aes_encrypt()/ecpay_aes_decrypt() 完整實作見 guides/14 §C
   ⚠️ 正式環境：MerchantID / HashKey / HashIV 須從環境變數讀取，不可寫死 */

#include <stdio.h>
#include <string.h>
#include <stdlib.h>
#include <time.h>
#include <curl/curl.h>
#include <cjson/cJSON.h>

/* 來自 guides/14 §C */
char *ecpay_aes_encrypt(const char *json_str, const char *hash_key, const char *hash_iv);
char *ecpay_aes_decrypt(const char *cipher_text, const char *hash_key, const char *hash_iv);

/* libcurl 寫回呼 */
struct MemBuf { char *data; size_t size; };
static size_t write_cb(void *ptr, size_t sz, size_t nmemb, void *ud) {
    struct MemBuf *mb = ud;
    size_t n = sz * nmemb;
    mb->data = realloc(mb->data, mb->size + n + 1);
    memcpy(mb->data + mb->size, ptr, n);
    mb->size += n;
    mb->data[mb->size] = '\0';
    return n;
}

int main(void) {
    /* 1. 組裝內層請求 JSON */
    cJSON *req = cJSON_CreateObject();
    cJSON_AddStringToObject(req, "MerchantID", "2000132");
    char relate_no[24];
    snprintf(relate_no, sizeof(relate_no), "INV%ld", (long)time(NULL));
    cJSON_AddStringToObject(req, "RelateNumber", relate_no);
    cJSON_AddStringToObject(req, "CustomerEmail", "test@example.com");
    cJSON_AddStringToObject(req, "Print", "0");
    cJSON_AddStringToObject(req, "Donation", "0");
    cJSON_AddStringToObject(req, "TaxType", "1");
    cJSON_AddNumberToObject(req, "SalesAmount", 100);
    cJSON *items = cJSON_AddArrayToObject(req, "Items");
    cJSON *item = cJSON_CreateObject();
    cJSON_AddStringToObject(item, "ItemName", "測試商品");
    cJSON_AddNumberToObject(item, "ItemCount", 1);
    cJSON_AddStringToObject(item, "ItemWord", "件");
    cJSON_AddNumberToObject(item, "ItemPrice", 100);
    cJSON_AddStringToObject(item, "ItemTaxType", "1");
    cJSON_AddNumberToObject(item, "ItemAmount", 100);
    cJSON_AddItemToArray(items, item);
    cJSON_AddStringToObject(req, "InvType", "07");
    char *inner_json = cJSON_PrintUnformatted(req);
    cJSON_Delete(req);

    /* 2. AES-128-CBC 加密（guides/14 §C） */
    char *encrypted = ecpay_aes_encrypt(inner_json,
        "ejCk326UnaZWKisg", "q9jcZX8Ib9LM8wYk");
    free(inner_json);

    /* 3. 組裝外層 JSON */
    cJSON *outer = cJSON_CreateObject();
    cJSON_AddStringToObject(outer, "MerchantID", "2000132");
    cJSON *hdr = cJSON_AddObjectToObject(outer, "RqHeader");
    cJSON_AddNumberToObject(hdr, "Timestamp", (double)time(NULL));
    cJSON_AddStringToObject(hdr, "Revision", "3.0.0");
    cJSON_AddStringToObject(outer, "Data", encrypted);
    free(encrypted);
    char *body = cJSON_PrintUnformatted(outer);
    cJSON_Delete(outer);

    /* 4. libcurl POST，Content-Type: application/json */
    struct MemBuf resp = { malloc(1), 0 };
    CURL *curl = curl_easy_init();
    struct curl_slist *hdrs = curl_slist_append(NULL,
        "Content-Type: application/json");
    curl_easy_setopt(curl, CURLOPT_URL,
        "https://einvoice-stage.ecpay.com.tw/B2CInvoice/Issue");
    curl_easy_setopt(curl, CURLOPT_HTTPHEADER, hdrs);
    curl_easy_setopt(curl, CURLOPT_POSTFIELDS, body);
    curl_easy_setopt(curl, CURLOPT_WRITEFUNCTION, write_cb);
    curl_easy_setopt(curl, CURLOPT_WRITEDATA, &resp);
    CURLcode rc = curl_easy_perform(curl);
    curl_slist_free_all(hdrs);
    curl_easy_cleanup(curl);
    free(body);

    if (rc != CURLE_OK) {
        fprintf(stderr, "curl error: %s\n", curl_easy_strerror(rc));
        free(resp.data); return 1;
    }

    /* 5. 雙層錯誤檢查（TransCode → 解密 → RtnCode） */
    cJSON *outer_resp = cJSON_Parse(resp.data);
    free(resp.data);
    cJSON *trans_code = cJSON_GetObjectItem(outer_resp, "TransCode");
    if (!trans_code || trans_code->valueint != 1) {
        cJSON *msg = cJSON_GetObjectItem(outer_resp, "TransMsg");
        fprintf(stderr, "TransCode error: %s\n",
            msg ? msg->valuestring : "unknown");
        cJSON_Delete(outer_resp); return 1;
    }

    /* 6. 解密內層 Data（guides/14 §C） */
    cJSON *data_node = cJSON_GetObjectItem(outer_resp, "Data");
    char *decrypted = ecpay_aes_decrypt(data_node->valuestring,
        "ejCk326UnaZWKisg", "q9jcZX8Ib9LM8wYk");
    cJSON_Delete(outer_resp);

    cJSON *inner_resp = cJSON_Parse(decrypted);
    free(decrypted);
    cJSON *rtn_code = cJSON_GetObjectItem(inner_resp, "RtnCode");
    if (!rtn_code || rtn_code->valueint != 1) {
        cJSON *msg = cJSON_GetObjectItem(inner_resp, "RtnMsg");
        fprintf(stderr, "RtnCode error: %s\n",
            msg ? msg->valuestring : "unknown");
        cJSON_Delete(inner_resp); return 1;
    }

    cJSON *inv_no = cJSON_GetObjectItem(inner_resp, "InvoiceNo");
    printf("發票號碼: %s\n", inv_no ? inv_no->valuestring : "(none)");
    cJSON_Delete(inner_resp);
    return 0;
}
```

> **⚠️ AES vs CMV URL Encode 差異**：`ecpay_aes_encrypt()` 使用 AES 專用 URL encode（不做 strtolower、不做 .NET 替換）；`generate_check_mac_value()` 使用 CMV 專用 URL encode（strtolower + .NET 字元替換）。兩者不可互換。詳見 [guides/14 §C](./14-aes-encryption.md)。

### 已有加密實作參考

- CheckMacValue（SHA256/MD5）：[guides/13-checkmacvalue.md](./13-checkmacvalue.md) C/C++ 區段
- AES-128-CBC：[guides/14-aes-encryption.md](./14-aes-encryption.md) C/C++ 區段

### 記憶體安全提醒

- **C**：所有 `malloc` 配對 `free`，加密後務必 `memset` 清除敏感資料緩衝區
- **C++**：優先使用 `std::unique_ptr` / `std::vector`，避免手動記憶體管理
- **密鑰保護**：HashKey/HashIV 不要以全域字串常數存放，使用環境變數或安全儲存

---

## 跨語言測試驗證

完整測試向量（SHA256 / MD5 / 含特殊字元）及 12 語言驗證範例見 [guides/13-checkmacvalue.md §測試向量](./13-checkmacvalue.md)。

建議以 guides/13 提供的測試向量驗證你的語言實作，確認 CheckMacValue 與預期值一致後再進入整合測試。

---

## 電子收據 AES-GCM E2E 範例（V3.0+）

> **適用**：電子收據（`/Receipt/*` API）後台設定為 AES-GCM 模式時。其他 AES-JSON 服務仍用 CBC，請參考上方既有 E2E。
>
> 完整 12 語言 GCM 實作見 [guides/14 §AES-GCM 模式](./14-aes-encryption.md#aes-gcm-模式電子收據選用)。本節提供 **Go + TypeScript** 兩個完整 E2E（開立 + Callback 解密），其他語言請參考既有 CBC E2E 對照替換加解密函式。

### Go 電子收據開立 + GCM 加密（完整 E2E）

```go
// go.mod: module ecpay-receipt-gcm
package main

import (
    "bytes"
    "crypto/aes"
    "crypto/cipher"
    "crypto/rand"
    "encoding/base64"
    "encoding/json"
    "errors"
    "fmt"
    "io"
    "net/http"
    "net/url"
    "strings"
    "time"
)

const (
    MerchantID = "2000132"
    HashKey    = "ejCk326UnaZWKisg"  // 一般/公益收據帳號
    EndpointIssue = "https://einvoice-stage.ecpay.com.tw/Receipt/Issue"
)

type ReceiptRequest struct {
    MerchantID string `json:"MerchantID"`
    RqHeader   struct {
        Timestamp int64 `json:"Timestamp"`
    } `json:"RqHeader"`
    Data string `json:"Data"`
}

type ReceiptResponse struct {
    TransCode int    `json:"TransCode"`
    TransMsg  string `json:"TransMsg"`
    Data      string `json:"Data"`
}

// aesUrlEncode — Go url.QueryEscape 不編碼 ~!*'()，需手動補齊（見 guides/14 §AES-GCM Go）
var aesReplacer = strings.NewReplacer("~", "%7E", "!", "%21", "*", "%2A", "'", "%27", "(", "%28", ")", "%29")

func aesGcmEncrypt(plaintextJSON string) (string, error) {
    // 1. aesUrlEncode（空格→+ 由 QueryEscape 處理，其餘手動補）
    urlEncoded := aesReplacer.Replace(url.QueryEscape(plaintextJSON))
    // 2. 產生隨機 12B IV
    iv := make([]byte, 12)
    if _, err := rand.Read(iv); err != nil {
        return "", err
    }
    // 3. AES-128-GCM
    block, _ := aes.NewCipher([]byte(HashKey)[:16])
    gcm, _ := cipher.NewGCMWithNonceSize(block, 12)
    // Seal(dst=iv, nonce=iv, pt, aad=nil) → iv || ciphertext || tag
    sealed := gcm.Seal(iv, iv, []byte(urlEncoded), nil)
    return base64.StdEncoding.EncodeToString(sealed), nil
}

func aesGcmDecrypt(encryptedB64 string) (string, error) {
    raw, err := base64.StdEncoding.DecodeString(encryptedB64)
    if err != nil {
        return "", err
    }
    if len(raw) < 12+16 {
        return "", errors.New("ciphertext too short")
    }
    iv := raw[:12]
    ctTag := raw[12:]
    block, _ := aes.NewCipher([]byte(HashKey)[:16])
    gcm, _ := cipher.NewGCMWithNonceSize(block, 12)
    pt, err := gcm.Open(nil, iv, ctTag, nil) // Tag 失敗回傳 error
    if err != nil {
        return "", fmt.Errorf("GCM decrypt: %w", err)
    }
    urlDecoded, _ := url.QueryUnescape(string(pt))
    return urlDecoded, nil
}

func issueReceipt() error {
    // 準備 Data（電子收據 Issue 必填欄位）
    data := map[string]interface{}{
        "MerchantID":      MerchantID,
        "Amount":          100,
        "Name":            "王小明",
        "ReceiptType":     1,             // 1=一般
        "RetrievalMethod": 2,             // 2=電子
        "ReceiptDate":     time.Now().Format("2006/01/02 15:04:05"),
        "RelateNumber":    fmt.Sprintf("RCPT%d", time.Now().Unix()),
        "Email":           "test@example.com",
        "Items": []map[string]interface{}{{
            "ItemSeq": 1, "ItemName": "測試商品",
            "ItemCount": 1, "ItemPrice": 100, "ItemAmount": 100,
        }},
    }
    dataJSON, _ := json.Marshal(data)

    // GCM 加密
    encrypted, err := aesGcmEncrypt(string(dataJSON))
    if err != nil {
        return err
    }

    // 組 Request
    req := ReceiptRequest{
        MerchantID: MerchantID,
        Data:       encrypted,
    }
    req.RqHeader.Timestamp = time.Now().Unix() // UTC+8，10 分鐘內有效
    body, _ := json.Marshal(req)

    // POST
    resp, err := http.Post(EndpointIssue, "application/json", bytes.NewReader(body))
    if err != nil {
        return err
    }
    defer resp.Body.Close()
    respBody, _ := io.ReadAll(resp.Body)

    // 外層錯誤檢查
    var parsed ReceiptResponse
    json.Unmarshal(respBody, &parsed)
    if parsed.TransCode != 1 {
        return fmt.Errorf("傳輸失敗: %s", parsed.TransMsg)
    }

    // 解密 Data（GCM）
    decrypted, err := aesGcmDecrypt(parsed.Data)
    if err != nil {
        return err
    }
    var result map[string]interface{}
    json.Unmarshal([]byte(decrypted), &result)

    // 內層錯誤檢查
    if int(result["RtnCode"].(float64)) != 1 {
        return fmt.Errorf("業務失敗: %v", result["RtnMsg"])
    }

    fmt.Printf("開立成功：ReceiptNo = %v\n", result["ReceiptNo"])
    return nil
}

func main() {
    if err := issueReceipt(); err != nil {
        fmt.Printf("錯誤: %v\n", err)
    }
}
```

### TypeScript 電子收據開立 + GCM 加密（完整 E2E）

```typescript
// 依賴：npm install typescript @types/node
//      tsc target: ES2020+，Node.js 18+（built-in fetch）
import * as crypto from 'crypto';

const MERCHANT_ID = '2000132';
const HASH_KEY = 'ejCk326UnaZWKisg';  // 電子收據一般/公益帳號
const ENDPOINT_ISSUE = 'https://einvoice-stage.ecpay.com.tw/Receipt/Issue';

interface ReceiptRequest {
    MerchantID: string;
    RqHeader: { Timestamp: number };  // ⚠️ 電子收據 RqHeader 不需 Revision
    Data: string;                      // AES-GCM 加密後的 Base64
}

interface ReceiptResponse {
    TransCode: number;  // 1 = 傳輸成功
    TransMsg: string;
    Data: string;       // AES-GCM 加密的回應內容
}

interface IssueResult {
    RtnCode: number;    // 1 = 業務成功（整數，非字串）
    RtnMsg: string;
    ReceiptNo: string;  // 綠界收據編號
}

// aesUrlEncode — 與 CBC 版本共用；補齊 encodeURIComponent 不編碼的字元
function aesUrlEncode(s: string): string {
    return encodeURIComponent(s)
        .replace(/~/g, '%7E').replace(/!/g, '%21').replace(/'/g, '%27')
        .replace(/\(/g, '%28').replace(/\)/g, '%29').replace(/\*/g, '%2A')
        .replace(/%20/g, '+');
}

function aesGcmEncrypt(plaintextJson: string): string {
    const iv = crypto.randomBytes(12);  // 生產：每次請求隨機 12B IV
    const urlEncoded = aesUrlEncode(plaintextJson);
    const key = Buffer.from(HASH_KEY, 'utf8').subarray(0, 16);
    const cipher = crypto.createCipheriv('aes-128-gcm', key, iv);
    const ct = Buffer.concat([cipher.update(urlEncoded, 'utf8'), cipher.final()]);
    const tag = cipher.getAuthTag();
    // IV (12B) || Ciphertext || Tag (16B) → Base64
    return Buffer.concat([iv, ct, tag]).toString('base64');
}

function aesGcmDecrypt(encryptedB64: string): string {
    const raw = Buffer.from(encryptedB64, 'base64');
    if (raw.length < 12 + 16) throw new Error('ciphertext too short');
    const iv = raw.subarray(0, 12);
    const tag = raw.subarray(raw.length - 16);
    const ct = raw.subarray(12, raw.length - 16);
    const key = Buffer.from(HASH_KEY, 'utf8').subarray(0, 16);

    const decipher = crypto.createDecipheriv('aes-128-gcm', key, iv);
    decipher.setAuthTag(tag);
    const pt = Buffer.concat([decipher.update(ct), decipher.final()]);  // Tag 失敗 throw
    return decodeURIComponent(pt.toString('utf8').replace(/\+/g, '%20'));
}

async function issueReceipt(): Promise<IssueResult> {
    const data = {
        MerchantID: MERCHANT_ID,
        Amount: 100,
        Name: '王小明',
        ReceiptType: 1,                                    // 1=一般收據
        RetrievalMethod: 2,                                // 2=電子
        ReceiptDate: new Date().toLocaleString('sv-SE', { timeZone: 'Asia/Taipei' }),
        RelateNumber: `RCPT${Date.now()}`,                 // 唯一編號
        Email: 'test@example.com',
        Items: [{
            ItemSeq: 1, ItemName: '測試商品',
            ItemCount: 1, ItemPrice: 100, ItemAmount: 100,
        }],
    };

    const body: ReceiptRequest = {
        MerchantID: MERCHANT_ID,
        RqHeader: { Timestamp: Math.floor(Date.now() / 1000) },  // UTC+8，10 分鐘內有效
        Data: aesGcmEncrypt(JSON.stringify(data)),
    };

    const resp = await fetch(ENDPOINT_ISSUE, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
    });
    const parsed = await resp.json() as ReceiptResponse;

    // 外層錯誤檢查（TransCode 為整數 1 才成功）
    if (parsed.TransCode !== 1) {
        throw new Error(`傳輸失敗：${parsed.TransMsg}`);
    }

    // 解密 + 內層錯誤檢查
    const decrypted = aesGcmDecrypt(parsed.Data);
    const result = JSON.parse(decrypted) as IssueResult;
    if (result.RtnCode !== 1) {  // ⚠️ 整數比較，非 '1' 字串
        throw new Error(`業務失敗：${result.RtnMsg}`);
    }

    console.log(`開立成功：ReceiptNo = ${result.ReceiptNo}`);
    return result;
}

issueReceipt().catch(err => console.error('錯誤:', err));
```

### TypeScript 電子收據 Callback Handler + GCM 解密（Express）

```typescript
// 依賴：npm install express @types/express @types/node
// 沿用上方 aesUrlEncode / aesGcmDecrypt；此處只展示 Callback handler 骨架
import express, { Request, Response } from 'express';
import * as crypto from 'crypto';

const HASH_KEY = 'ejCk326UnaZWKisg'; // 電子收據一般/公益帳號

interface ReceiptCallback {
    MerchantID: string;
    RqHeader: { Timestamp: number };
    Data: string; // AES-GCM 加密
}

interface DecryptedData {
    MerchantID: string;
    ReceiptNo: string;
    Notified?: 'C' | 'M' | 'A';
    NotifyTag?: 1 | 2;
    [key: string]: unknown;
}

function aesGcmDecrypt(encryptedB64: string): DecryptedData {
    const raw = Buffer.from(encryptedB64, 'base64');
    if (raw.length < 12 + 16) throw new Error('ciphertext too short');
    const iv = raw.subarray(0, 12);
    const tag = raw.subarray(raw.length - 16);
    const ct = raw.subarray(12, raw.length - 16);
    const key = Buffer.from(HASH_KEY, 'utf8').subarray(0, 16);

    const decipher = crypto.createDecipheriv('aes-128-gcm', key, iv);
    decipher.setAuthTag(tag);
    const pt = Buffer.concat([decipher.update(ct), decipher.final()]); // Tag 失敗 throw
    const urlDecoded = decodeURIComponent(pt.toString('utf8').replace(/\+/g, '%20'));
    return JSON.parse(urlDecoded) as DecryptedData;
}

const app = express();
app.use(express.json());

app.post('/ecpay/receipt/callback', (req: Request<{}, {}, ReceiptCallback>, res: Response) => {
    try {
        // 1. 外層錯誤檢查（此範例假設是特店自己呼叫 Notification API 後綠界的回應）
        //    實際 Callback 場景中 TransCode 已由綠界前端檢查過，此處直接解密

        // 2. GCM 解密 Data
        const decrypted = aesGcmDecrypt(req.body.Data);
        console.log(`[Receipt Callback] ReceiptNo=${decrypted.ReceiptNo}`);

        // 3. 業務處理（冪等）
        //    使用 ReceiptNo + RelateNumber 為 key 做 upsert，避免綠界重送 4 次造成重複

        // 4. 回應綠界（JSON 格式，不是 1|OK）
        res.status(200).json({ RtnCode: 1, RtnMsg: 'Success' });
    } catch (err) {
        // Tag 驗證失敗會進到這裡 → 代表資料被竄改或 Key 錯誤
        console.error('[Receipt Callback] Decrypt failed:', err);
        res.status(200).json({ RtnCode: 0, RtnMsg: 'Decrypt failed' });
    }
});

app.listen(3000, () => console.log('Receipt callback server on :3000'));
```

> ⚠️ **GCM 生產環境 IV 必須隨機**：上方 Go 範例使用 `rand.Read(iv)` 每次產生新 IV；TypeScript Callback handler 只做解密（IV 由綠界產生並放在密文前 12 byte）。**切勿在生產環境使用 test-vectors 的固定 IV `112233445566778899aabbcc`**。

---

## Production 環境切換 Checklist

完整上線檢查清單見 [guides/16-go-live-checklist.md](./16-go-live-checklist.md)。

> **關鍵原則**：所有語言均應從環境變數讀取 MerchantID / HashKey / HashIV，禁止寫死在程式碼中。
> 環境 URL 對照表見 [SKILL.md §快速參考](../SKILL.md)。

## 相關文件

- [guides/00-getting-started.md](./00-getting-started.md) — 入門：PHP/Node.js/Python Quick Start
- [guides/13-checkmacvalue.md](./13-checkmacvalue.md) — CheckMacValue 12 語言實作
- [guides/14-aes-encryption.md](./14-aes-encryption.md) — AES 加解密 12 語言實作
- [guides/19-http-protocol-reference.md](./19-http-protocol-reference.md) — HTTP 協議參考
- [guides/16-go-live-checklist.md](./16-go-live-checklist.md) — 上線檢查清單
- `references/` — 官方 API 文件 URL 索引（生成程式碼前應 web_fetch 取得最新規格）
