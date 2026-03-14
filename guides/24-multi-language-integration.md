> 對應 ECPay API 版本 | 最後更新：2026-03

<!-- AI Section Index（精確行號，2026-03-14 校準）
Go E2E: line 113-455 (CMV: 115-250, AES: 252-455)
Java 差異指南: line 458-500 | C# 差異指南: line 502-542
TypeScript: line 544-601
Kotlin 差異指南: line 604-645 | Ruby 差異指南: line 647-686
Swift 差異指南: line 688-726 | Rust 差異指南: line 728-767
Mobile App: line 769-805 | 非 PHP CMV Checklist: line 807-825
非 PHP AES-JSON Checklist: line 827-844
E2E 組裝步驟: line 846-857 | C/C++ 注意事項: line 859-975
跨語言測試: line 977-983 | Production 環境切換: line 985-990
-->

# 多語言整合完整指南

> 📌 **語言規範**：生成目標語言程式碼時，同時載入 `guides/lang-standards/{語言}.md`（命名慣例、型別定義、錯誤處理、HTTP 設定等），確保產出的程式碼為 idiomatic 且生產就緒。

## 語言快速導航

| 語言 | CMV-SHA256 (AIO) | AES-JSON (發票) | 類型 | 位置 / 行號 |
|------|:-:|:-:|:-:|---------|
| **Go** | ✅ Web Server | ✅ B2C 發票 | 完整 E2E | line 113-455 |
| **Java** | ✅ | ✅ | 差異指南 | line 458-500 |
| **C#** | ✅ | ✅ | 差異指南 | line 502-542 |
| **TypeScript** | → Node.js | → Node.js | 型別定義 | line 544-601 |
| **Kotlin** | ✅ | ✅ | 差異指南 | line 604-645 |
| **Ruby** | ✅ | ✅ | 差異指南 | line 647-686 |
| **Swift** | ✅ | ✅ | 差異指南 | line 688-726 |
| **Rust** | ✅ | ✅ | 差異指南 | line 728-767 |
| **Python** | ✅ | ✅ | 完整 E2E | → [guides/00](./00-getting-started.md) §Quick Start |
| **Node.js** | ✅ | ✅ | 完整 E2E | → [guides/00](./00-getting-started.md) §Quick Start |
| **Mobile App** | — | — | iOS + Android 指引 | line 769-805 |
| **C/C++** | — | — | 整合注意事項 | line 859-975 |

> **只需看你的語言**：使用 AI Section Index 行號範圍只讀取對應區段，不需載入全文。
> **只需加密函式？** → [guides/13 CheckMacValue](./13-checkmacvalue.md) 或 [guides/14 AES](./14-aes-encryption.md)（12 語言全覆蓋）。

### 設計原則

- **Go** 為完整參考實作（CMV-SHA256 + AES-JSON），其他語言提供差異指南（依賴、關鍵差異、注意事項），AI 助手根據 Go 基底 + 差異 + guides/13-14 翻譯完整程式碼
- **TypeScript** 開發者請直接使用 Node.js 範例（[guides/00](./00-getting-started.md)）+ 型別標注，加密函式見 guides/13-14 §TypeScript
- AI 生成其他語言程式碼時，會基於本指南的 E2E 結構 + guides/13-14 的加密實作 + guides/20 的 HTTP 協議規格進行翻譯

> ### ⚠️ AI 組裝指令（差異指南使用規則）
>
> Java/C#/Kotlin/Ruby/Swift/Rust 區段僅列出與 Go 的差異，**不是可獨立執行的程式碼**。
> 生成完整實作時必須遵守：
>
> 1. **加密函式禁止從 Go 直譯** — 必須使用 [guides/13](./13-checkmacvalue.md) 和 [guides/14](./14-aes-encryption.md) 中該語言的專屬實作（含 URL encode 差異、padding 處理、JSON 序列化注意事項）
> 2. **組裝順序**：先讀 guides/13-14 目標語言區段 → 再讀本文 Go E2E 了解業務流程 → 最後套用差異指南的依賴和 API 差異
> 3. **必驗項目**：用 `test-vectors/` 驗證加密輸出正確性，再整合到 E2E 流程

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
- 已讀 [guides/20-http-protocol-reference.md](./20-http-protocol-reference.md)（HTTP 協議規格）
- 已讀 [guides/13-checkmacvalue.md](./13-checkmacvalue.md)（CMV-SHA256/CMV-MD5 認證）或 [guides/14-aes-encryption.md](./14-aes-encryption.md)（AES-JSON 認證）

**涵蓋語言**：Go（完整 E2E）、Java/C#/Kotlin/Ruby/Swift/Rust（差異指南）、TypeScript（型別定義）+ 全 12 語言通用參考

## HTTP Client 推薦表

| 語言 | 推薦 Client | 最低版本 | 安裝命令 | Timeout 設定 |
|------|------------|---------|---------|-------------|
| Go | net/http (stdlib) | Go 1.21+ | — | `client.Timeout = 30 * time.Second` |
| Java | java.net.http.HttpClient | JDK 11+ | — | `connectTimeout(Duration.ofSeconds(30))` |
| C# | HttpClient | .NET 6+ | — | `Timeout = TimeSpan.FromSeconds(30)` |
| Node.js | built-in fetch / axios | Node 18+ / axios 1.7+ | `npm install axios` | `signal: AbortSignal.timeout(30000)` |
| Python | httpx (async) / requests (sync) | httpx 0.27+ / requests 2.32+ | `pip install httpx` | `timeout=30.0` |
| Rust | reqwest | 0.12+ | `cargo add reqwest` | `timeout(Duration::from_secs(30))` |
| Swift | URLSession | iOS 13+ / macOS 10.15+ | — | `timeoutIntervalForRequest = 30` |
| Kotlin | java.net.http.HttpClient | JDK 11+ | — | `connectTimeout(Duration.ofSeconds(30))` |
| Ruby | Net::HTTP (stdlib) | Ruby 3.0+ | — | `open_timeout = 30; read_timeout = 30` |
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
	merchantID = "3002607"
	hashKey    = "pwFHCqoQZGmho4w6"
	hashIV     = "EkRm7iFT261dpevs"
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
	invoiceMerchantID = "2000132"
	invoiceHashKey    = "ejCk326UnaZWKisg"
	invoiceHashIV     = "q9jcZX8Ib9LM8wYk"
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
	return encoded
}

// 完整實作見 guides/14-aes-encryption.md §Go
// AES 專用 URL encode — 不做 toLowerCase 和 .NET 還原（與 CMV ecpayURLEncode 不同）
// url.QueryEscape("~") = "~"（RFC 3986 unreserved），但 PHP urlencode("~") = "%7E"，需手動補
func aesURLEncode(s string) string {
	encoded := url.QueryEscape(s)
	return strings.ReplaceAll(encoded, "~", "%7E")
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

	resp, err := http.Post(invoiceURL, "application/json", bytes.NewReader(bodyBytes))
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


## Java 整合差異指南（基於 Go 參考實作）

> 完整 E2E 結構同 Go 範例。以下僅列出 Java 與 Go 的關鍵差異。
> AI 助手：**加密函式必須取自 [guides/13 §Java](./13-checkmacvalue.md) 和 [guides/14 §Java](./14-aes-encryption.md)**，不可從 Go 直譯。業務流程參考 Go E2E + 以下差異。

### 環境與依賴

- **最低版本**：JDK 11+（java.net.http.HttpClient 內建）
- **零外部依賴**：純 JDK 實作（HttpServer + HttpClient + MessageDigest）
- 安裝：無需額外安裝（JSON 處理需 Gson）

### 與 Go 的關鍵差異

| 項目 | Go | Java |
|------|-----|------|
| HTTP Server | net/http | com.sun.net.httpserver.HttpServer |
| HTTP Client | http.Post | java.net.http.HttpClient |
| URL Encode | url.QueryEscape | URLEncoder.encode(s, StandardCharsets.UTF_8) |
| SHA256 | crypto/sha256 | MessageDigest.getInstance("SHA-256") |
| AES | crypto/aes + cipher | javax.crypto.Cipher + SecretKeySpec |
| JSON | encoding/json | Gson（需 `disableHtmlEscaping` + `LinkedHashMap` 保序） |
| Form Parse | r.ParseForm() | 手動解析 InputStream |

### Java 特有注意事項

1. **URLEncoder 差異**：`URLEncoder.encode()` 將空格編碼為 `+`，波浪號 `~` 編碼為 `%7E`（與 PHP urlencode 一致），但 **星號 `*` 不編碼**（JVM URLEncoder safe set 包含 `*`）— **AES URL encode 必須手動替換 `*→%2A`**；CMV 不受影響（PHP 的 .NET replacement `%2a→*` 使最終結果一致）；完整實作見 [guides/14 §Java](./14-aes-encryption.md)
2. **JSON key 順序**：`HashMap` 不保證順序 — AES-JSON 必須用 `LinkedHashMap`
3. **Gson HTML 轉義**：預設會轉義 `<>&` — 必須 `new GsonBuilder().disableHtmlEscaping().create()`
4. **Hex 格式化**：`String.format("%02x", b)` 產生小寫 hex，CMV 不影響（有 toLowerCase），AES 需確認大寫
5. **CheckMacValue**：完整實作見 [guides/13 §Java](./13-checkmacvalue.md)
6. **AES 加解密**：完整實作見 [guides/14 §Java](./14-aes-encryption.md)

### 執行方式

需要 Gson：下載 `gson-2.11.0.jar` 或用 Maven/Gradle

```bash
# 編譯與執行
javac -cp gson-2.11.0.jar EcpayDemo.java
java -cp .:gson-2.11.0.jar EcpayDemo
```

---

## C# 整合差異指南（基於 Go 參考實作）

> 完整 E2E 結構同 Go 範例。以下僅列出 C# 與 Go 的關鍵差異。
> AI 助手：**加密函式必須取自 [guides/13 §C#](./13-checkmacvalue.md) 和 [guides/14 §C#](./14-aes-encryption.md)**，不可從 Go 直譯。業務流程參考 Go E2E + 以下差異。

### 環境與依賴

- **最低版本**：.NET 6+（HttpClient、System.Text.Json 內建）
- **零外部依賴**：純 .NET 實作（ASP.NET Core Minimal API）
- 安裝：`dotnet new web -n EcpayDemo`

### 與 Go 的關鍵差異

| 項目 | Go | C# |
|------|-----|------|
| HTTP Server | net/http | ASP.NET Core Minimal API (`app.MapPost`) |
| HTTP Client | http.Post | HttpClient + FormUrlEncodedContent |
| URL Encode | url.QueryEscape | WebUtility.UrlEncode（注意：不同於 HttpUtility.UrlEncode） |
| SHA256 | crypto/sha256 | System.Security.Cryptography.SHA256 |
| AES | crypto/aes + cipher | System.Security.Cryptography.Aes |
| JSON | encoding/json | System.Text.Json.JsonSerializer |
| Form Parse | r.ParseForm() | `await Request.ReadFormAsync()` |

### C# 特有注意事項

1. **WebUtility vs HttpUtility**：`WebUtility.UrlEncode` 將空格編碼為 `+`，波浪號 `~` 不編碼 — 需手動替換 `~→%7E`。不要用 `HttpUtility.UrlEncode`（行為不同）
2. **JSON key 順序**：class 屬性定義順序即為 JSON key 順序 — 不需額外設定
3. **HTML 轉義問題**：`System.Text.Json` **預設轉義** `<>&+'` — 必須設定 `JsonSerializerOptions { Encoder = JavaScriptEncoder.UnsafeRelaxedJsonEscaping }` 才能輸出正確 JSON（同 line 103 對照表所示）
4. **AES Padding**：.NET 的 `Aes.Create()` 預設使用 PKCS7 padding（與 ECPay 需求一致）
5. **CheckMacValue**：完整實作見 [guides/13 §C#](./13-checkmacvalue.md)
6. **AES 加解密**：完整實作見 [guides/14 §C#](./14-aes-encryption.md)

### 執行方式

```bash
dotnet run
```

> **C# HttpClient 注意**：`HttpClient` 應宣告為 `static readonly` 或透過 `IHttpClientFactory`（.NET 5+）注入，避免重複建立導致 socket exhaustion。

---

## TypeScript 整合指引

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

### 環境與依賴

- **最低版本**：Kotlin/JVM 1.9+（JDK 11+）
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

### 環境與依賴

- **最低版本**：Ruby 3.0+
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

### 環境與依賴

- **最低版本**：Swift 5.9+（iOS 13+ / macOS 10.15+）
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

## 非 PHP AES-JSON 統一 Checklist（ECPG / 發票 / 全方位物流 / 電子票證）

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

C/C++ 極少用於 Web 整合，因此不提供完整 E2E 範例。以下為必要的依賴和建置資訊。

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

> **ReturnURL Callback**：ECPay 伺服器交易完成後會 POST 至 `ReturnURL`，你的 C 伺服器（或其他語言）必須回應純字串 `1|OK`（無 HTML、無 BOM）。見 [guides/22 §CMV-SHA256 Callback](./22-webhook-events-reference.md)。

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

## Production 環境切換 Checklist

完整上線檢查清單見 [guides/16-go-live-checklist.md](./16-go-live-checklist.md)。

> **關鍵原則**：所有語言均應從環境變數讀取 MerchantID / HashKey / HashIV，禁止寫死在程式碼中。
> 環境 URL 對照表見 [SKILL.md §快速參考](../SKILL.md)。

## 相關文件

- [guides/00-getting-started.md](./00-getting-started.md) — 入門：PHP/Node.js/Python Quick Start
- [guides/13-checkmacvalue.md](./13-checkmacvalue.md) — CheckMacValue 12 語言實作
- [guides/14-aes-encryption.md](./14-aes-encryption.md) — AES 加解密 12 語言實作
- [guides/20-http-protocol-reference.md](./20-http-protocol-reference.md) — HTTP 協議參考
- [guides/16-go-live-checklist.md](./16-go-live-checklist.md) — 上線檢查清單
- `references/` — 官方 API 文件 URL 索引（生成程式碼前應 web_fetch 取得最新規格）
