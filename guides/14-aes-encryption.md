> 對應 ECPay API 版本 | 基於 PHP SDK ecpay/sdk | 最後更新：2026-04

<!-- AI Section Index（供 AI 部分讀取大檔案用，2026-04-23 校準）
== AES-CBC 實作 ==
Python: line 255-302 | Node.js: line 303-351 | TypeScript: line 352-405
Java: line 407-492 | C#: line 494-555 | Go: line 557-670
C: line 672-874 | C++: line 876-1029 | Rust: line 1031-1096
Swift: line 1098-1198 | Kotlin: line 1203-1258 | Ruby: line 1260-1308

== AES-GCM 實作（電子收據 V3.0+ 選用）==
AES-GCM 章節起點: line 1322 | GCM Python: line 1382-1411 | GCM Node.js: line 1413-1448
GCM TypeScript: line 1450-1489 | GCM Java: line 1491-1546 | GCM C#: line 1548-1607
GCM Go: line 1609-1685 | GCM C: line 1687-1745 | GCM C++: line 1747-1802
GCM Rust: line 1804-1869 | GCM Swift: line 1871-1925 | GCM Kotlin: line 1927-1975
GCM Ruby: line 1977-2026 | GCM 特有常見錯誤: line 2028-2039

== 測試向量與常見錯誤 ==
測試向量: line 2041-2206 | 常見錯誤: line 2208+
CI/自動化驗證: test-vectors/aes-encryption.json (13 vectors: 9 CBC + 4 GCM) + test-vectors/url-encode-comparison.json (4 vectors) + test-vectors/verify.py
-->

**快速跳轉**: [Python](#python) | [Node.js](#nodejs) | [TypeScript](#typescript) | [Java](#java) | [C#](#c) | [Go](#go) | [C](#c-1) | [C++](#c-2) | [Rust](#rust) | [Swift](#swift) | [Kotlin](#kotlin) | [Ruby](#ruby)

> 🔗 **正在串接 ECPG 線上金流服務（如站內付 2.0、幕後授權、幕後取號）？**
>
> 本文件提供各語言的 AES 加解密函式。ECPG AES-JSON 協議串接需同時注意：
> - **`aesUrlEncode` vs `ecpayUrlEncode`**：ECPG 各服務使用 `aesUrlEncode`（本文件定義的輔助函式名，PHP SDK 中直接使用 `urlencode()`，只做 URL 編碼，不做 lowercase 和 .NET 字元替換）；AIO 金流使用 `ecpayUrlEncode`。**兩者絕對不可混用**，否則 `TransCode ≠ 1`。
> - **RqHeader 格式**：ECPG 各服務的 `RqHeader` **只有 `Timestamp`**（Unix 秒），**不加 `Revision`**（電子發票 / 物流才有）。
> - **完整串接流程（站內付 2.0）**：本文件提供加解密函式；完整的「GetTokenbyTrade → CreatePayment → Callback」5 步驟流程請見 [guides/02 §首次串接快速路徑](./02-payment-ecpg.md)。
>
> AES vs CMV URL Encode 對比表見本文件 §AES vs CMV URL Encode 對比表。

# AES 加解密完整解說

> 📌 **語言規範**：生成目標語言程式碼時，同時載入 `guides/lang-standards/{語言}.md`（命名慣例、型別定義、錯誤處理、HTTP 設定等），確保產出的程式碼為 idiomatic 且生產就緒。

## 概述

> 💡 **驗證提醒**：完成任何語言的實作後，務必使用 `test-vectors/aes-encryption.json` 的 9 個測試向量 + `test-vectors/url-encode-comparison.json` 的 4 個向量驗證輸出。執行 `python test-vectors/verify.py` 可一次驗證所有向量。

AES-128-CBC 加密用於站內付 2.0、電子發票、全方位物流、跨境物流、ECTicket、**電子收據**。前四項服務不使用 CheckMacValue；ECTicket同時使用 AES 加密與 CheckMacValue（雙重驗證），詳見 [guides/09](./09-ecticket.md)；**電子收據（V3.0+）另支援 AES-128-GCM 作為選用加密模式**（預設仍為 CBC，需於綠界後台切換），詳見本文件 [§AES-GCM 模式](#aes-gcm-模式電子收據選用) 與 [guides/25](./25-receipt.md)。

> 💡 **驗證你的實作**：完成後使用 [`test-vectors/aes-encryption.json`](../test-vectors/aes-encryption.json)（9 個向量）和 [`test-vectors/url-encode-comparison.json`](../test-vectors/url-encode-comparison.json)（4 個向量）驗證正確性，或執行 `python test-vectors/verify.py` 自動化驗證。

## AES 和 CheckMacValue 有什麼不同？

| 比較 | CheckMacValue (CMV) | AES 加解密 |
|------|-------------------|-----------|
| **用途** | 驗證資料未被竄改（簽章） | 加密敏感資料（機密性） |
| **複雜度** | 簡單（排序→串接→雜湊） | 較複雜（URL encode→加密→Base64） |
| **適用服務** | AIO 金流、國內物流 | ECPG、發票、全方位物流、票證 |
| **學習順序** | 先學這個（guides/13） | 再學這個（本文件） |
| **運算成本** | < 1ms | < 10ms |

> 如果你只用 AIO 金流，只需學 CheckMacValue（[guides/13](./13-checkmacvalue.md)），不需要本文件。
> 使用 ECPG、發票、或全方位物流時才需要 AES。

## 使用場景

| 服務 | RqHeader.Revision | 特殊欄位 |
|------|-------------------|---------|
| 站內付 2.0 | 不使用 | — |
| 幕後授權 | 不使用 | — |
| 幕後取號 | 不使用 | — |
| B2C 電子發票 | 3.0.0 | — |
| B2B 電子發票 | 1.0.0 | RqID ⚠️ |
| 全方位物流 | 1.0.0 | — |
| 跨境物流 | 1.0.0 | — |
| **電子收據**（V3.0+）| **不使用** | 加密模式**預設 AES-128-CBC，可切換為 AES-128-GCM**（後台設定）；GCM 的 IV 自產 12 byte、Tag 16 byte，見本文件 [§AES-GCM 模式](#aes-gcm-模式電子收據選用) |
| ECTicket | 不使用 | 外層另附 CheckMacValue（SHA256，公式見 [guides/09](./09-ecticket.md)）；RqHeader 僅需 `Timestamp`（官方規格無 Revision） |
| 直播收款 | 不使用 | 外層另附 CheckMacValue（SHA256，公式見 [guides/17 §直播收款](./17-hardware-services.md#直播收款指引)） |

> ⚠️ **B2B 電子發票 RqID 特別說明**：B2B 是所有 AES-JSON 服務中**唯一**在 `RqHeader` 中需要額外傳入 `RqID` 欄位的服務。`RqID` 格式為 UUID v4（如 `"550e8400-e29b-41d4-a716-446655440000"`），每次請求必須唯一，用於冪等性控制。其他 AES-JSON 服務（站內付 2.0、發票 B2C、全方位物流、ECTicket等）的 `RqHeader` 均無此欄位。詳見 [guides/05 §RqHeader 說明](./05-invoice-b2b.md)。

## 三層請求結構

```json
{
  "MerchantID": "特店編號",
  "RqHeader": {
    "Timestamp": 1234567890,
    "Revision": "版本號"
  },
  "Data": "Base64(AES-128-CBC(urlencode(JSON)))"
}
```

> **⚠️ JSON key 排序說明**：PHP SDK `AesRequest.php` 使用 `ArrayService::naturalSort()` 對外層 key 排序，實際送出順序為 `{Data, MerchantID, RqHeader}`（D < M < R 字母序）。一般 JSON 解析器忽略 key 順序，此排序對功能無影響，但嚴格序列化比對測試時需注意。

## 加解密流程

> 從 `scripts/SDK_PHP/src/Services/AesService.php` 精確對應(`encrypt()` @ line 91-110、`decrypt()` @ line 41-63)

### 加密（明文 → 密文）

```
1. json_encode($source)          → JSON 字串
2. urlencode()                   → URL 編碼（空格→+）
3. openssl_encrypt(              → AES 加密
     AES-128-CBC,
     OPENSSL_RAW_DATA,           → 輸出原始二進位（不自動 base64；若不加此旗標，PHP 會對輸出再 base64 一次，導致 step 4 雙重編碼）；PKCS7 padding 是 CBC 模式預設行為
     hashKey,
     hashIv
   )
4. base64_encode()               → Base64 編碼
```

### 解密（密文 → 明文）

```
1. base64_decode()               → 還原二進位
2. openssl_decrypt(              → AES 解密
     AES-128-CBC,
     OPENSSL_RAW_DATA,
     hashKey,
     hashIv
   )
3. urldecode()                   → URL 解碼
4. json_decode()                 → 還原陣列/物件
```

### 非常規順序警告

ECPay 的加解密順序是**非常規**的：
- **加密前先 URL encode**（一般做法是加密後才 encode）
- **解密後才 URL decode**（一般做法是 decode 後才解密）

這是 ECPay 獨有的設計，其他語言實作時必須嚴格遵守此順序。

### 雙層錯誤檢查（AES-JSON 回應）

AES-JSON 服務的回應需依序做兩層錯誤檢查：

1. **檢查 `TransCode`（外層）**：`1` = 傳輸成功，可繼續解密 `Data`；非 `1` = 加密/傳輸錯誤（通常是 URL Encode 或 HashKey/HashIV 不符）。
2. **解密 `Data` 後，檢查 `RtnCode`（內層）**：整數 `1` = 業務成功；非 `1` = 業務失敗，查看 `RtnMsg`。

> ⚠️ `RtnCode` 在 AES-JSON 服務中為**整數 `1`**（不是字串 `"1"`），與 AIO/物流 Callback 的字串 `"1"` 不同。
> ECTicket例外：需三層驗證（TransCode → 解密 Data → CheckMacValue → RtnCode），見 [guides/09](./09-ecticket.md)。

### AES vs CMV URL Encode 對比表

> **⚠️ 常見錯誤**：複製 CheckMacValue 的 `ecpayUrlEncode()` 用於 AES 加密會導致 ECPay API 解密失敗。
> 兩者的 URL Encode 邏輯**完全不同**。

| 步驟 | AES URL Encode | CMV ecpayUrlEncode |
|------|---------------|-------------------|
| URL 編碼 | `urlencode()` / `encodeURIComponent()` | `urlencode()` / `encodeURIComponent()` |
| 轉小寫 | **不做** | 全部轉小寫 |
| .NET 字元替換 | **不做** | `%2d→-`, `%5f→_`, `%2e→.`, `%21→!`, `%2a→*`, `%28→(`, `%29→)` |
| **`~` 處理** | **`%7E`（大寫 hex）**：PHP urlencode 輸出大寫；Python/Node.js 需手動加 `.replace('~', '%7E')`。AES URL Encode **不做 strtolower**，故 hex 保持大寫 `%7E`（所有語言實作均統一如此） | **`%7e`（小寫 hex）**：urlencode 後 strtolower 將大寫 `%7E` 轉為小寫 `%7e` |
| `!` `*` `(` `)` | **`%21 %2A %28 %29`**（AES 保留編碼，不還原）| `!` `*` `(` `)`（.NET 替換還原為原始字元）|
| `'` | **`%27`**（保留編碼） | **`%27`**（保留編碼，.NET 替換**不含**此字元）|
| 使用場景 | AES 加密前（AES-JSON 服務） | CheckMacValue 計算（CMV-SHA256/CMV-MD5） |

**PHP SDK 原始碼對照**:
- AES:`AesService.php:96`(encrypt 時)與 `AesService.php:56`(decrypt 時)→ 直接呼叫 PHP 內建 `urlencode()` / `urldecode()`,無 lowercase、無 .NET 替換
- CMV:`UrlService.php:13-48` → `urlencode()` + `strtolower()` + 7 字元 .NET 替換(`%2d`→`-`、`%5f`→`_`、`%2e`→`.`、`%21`→`!`、`%2a`→`*`、`%28`→`(`、`%29`→`)`)

**各語言正確的 AES URL Encode**：

```python
# Python — AES 專用（注意：不做 lower() 和 .NET 替換）
# quote_plus 不編碼 ~，但 PHP urlencode 會，需手動替換（' 已被 quote_plus 編碼為 %27，.replace 為冪等保險）
def aes_url_encode(source: str) -> str:
    encoded = urllib.parse.quote_plus(source)
    return encoded.replace('~', '%7E').replace("'", '%27')
```

```javascript
// Node.js — AES 專用
function aesUrlEncode(source) {
  return encodeURIComponent(source)
    .replace(/%20/g, '+')
    .replace(/~/g, '%7E')
    .replace(/!/g, '%21')
    .replace(/'/g, '%27')
    .replace(/\(/g, '%28')
    .replace(/\)/g, '%29')
    .replace(/\*/g, '%2A');
}
```

```go
// Go — AES 專用（QueryEscape 可能不編碼 ~，且 replace 其餘字元為冪等保險，確保匹配 PHP urlencode）
func aesURLEncode(s string) string {
    encoded := url.QueryEscape(s)
    r := strings.NewReplacer("~", "%7E", "!", "%21", "*", "%2A", "'", "%27", "(", "%28", ")", "%29")
    return r.Replace(encoded)
}
```

```java
// Java — AES 專用
static String aesUrlEncode(String source) throws Exception {
    return URLEncoder.encode(source, "UTF-8")
        .replace("!", "%21").replace("~", "%7E").replace("*", "%2A")
        .replace("'", "%27").replace("(", "%28").replace(")", "%29");
}
```

```typescript
// TypeScript — AES 專用
function aesUrlEncode(source: string): string {
  return encodeURIComponent(source)
    .replace(/%20/g, '+').replace(/~/g, '%7E')
    .replace(/!/g, '%21').replace(/'/g, '%27')
    .replace(/\(/g, '%28').replace(/\)/g, '%29').replace(/\*/g, '%2A');
}
```

```csharp
// C# — AES 專用（WebUtility.UrlEncode 大寫 hex，與 PHP 一致）
static string AesUrlEncode(string source) =>
    System.Net.WebUtility.UrlEncode(source)
        ?.Replace("%20", "+")     // 保險措施：WebUtility.UrlEncode 空格→+（與 PHP 相同），此 replace 為冪等安全措施
        .Replace("~", "%7E").Replace("!", "%21").Replace("*", "%2A")
        .Replace("'", "%27").Replace("(", "%28").Replace(")", "%29") ?? source;
```

```kotlin
// Kotlin — AES 專用
fun aesUrlEncode(source: String): String =
    URLEncoder.encode(source, StandardCharsets.UTF_8)
        .replace("!", "%21").replace("~", "%7E").replace("*", "%2A")
        .replace("'", "%27").replace("(", "%28").replace(")", "%29")
```

```ruby
# Ruby — AES 專用
def aes_url_encode(source)
  CGI.escape(source).gsub('~', '%7E')
      .gsub('!', '%21').gsub('*', '%2A')
      .gsub("'", '%27').gsub('(', '%28').gsub(')', '%29')
end
```

> 完整的各語言 CMV URL Encode 實作見 [guides/13-checkmacvalue.md](./13-checkmacvalue.md)。

## PHP 開發者

SDK 已自動處理：
- 發送請求：`PostWithAesJsonResponseService` 自動加密 Data
- 接收回應：同上，自動解密回應的 Data
- 手動操作：`$factory->create(AesService::class)`

```php
$aesService = $factory->create(AesService::class);
$encrypted = $aesService->encrypt($data);    // array → base64 string
$decrypted = $aesService->decrypt($encrypted); // base64 string → array
```

## 12 種語言完整實作

以下實作從 `AesService.php`(`encrypt()` @ line 91-110、`decrypt()` @ line 41-63)精確翻譯,涵蓋 Python、Node.js、TypeScript、Java、C#、Go、C、C++、Rust、Swift、Kotlin、Ruby。

### 加密規格
- 演算法：AES-128-CBC
- Key 長度：16 bytes（HashKey 的前 16 bytes；PHP SDK 傳入完整字串，OpenSSL 自動截取）
- IV 長度：16 bytes（HashIV 的前 16 bytes；其他語言需手動截取 `hashIV[:16]`）
- Padding：PKCS7
- 輸出：Base64（**必須使用標準 alphabet `+`、`/`、`=`，禁止使用 URL-safe alphabet `-`、`_`**；部分語言如 Go、Rust 預設為 URL-safe，須明確指定標準模式）

---

### Python

```python
import json
import base64
from urllib.parse import quote_plus, unquote_plus
from Crypto.Cipher import AES
from Crypto.Util.Padding import pad, unpad

def aes_encrypt(data: dict, hash_key: str, hash_iv: str) -> str:
    """對應 AesService::encrypt()"""
    # 1. JSON encode
    # ⚠️ ensure_ascii=False 是推薦設定（遺漏此參數是 Python 最常見的 AES 串接問題）：
    #   False → 保留原始 UTF-8 中文 → URL encode 產生較短結果，推薦使用
    #   True（預設）→ 中文轉為 \uXXXX（等同 PHP json_encode 預設行為）→ 結果亦正確但字串較長
    #   兩者 ECPay 皆可正常解密，但 False 在含中文時更節省位元組
    json_str = json.dumps(data, separators=(',', ':'), ensure_ascii=False)
    # 2. URL encode（空格→+）
    # quote_plus 不編碼 ~，但 PHP urlencode 會編碼為 %7E
    url_encoded = quote_plus(json_str).replace('~', '%7E').replace("'", '%27')
    # 3. AES-128-CBC + PKCS7
    key = hash_key.encode('utf-8')[:16]
    iv = hash_iv.encode('utf-8')[:16]
    cipher = AES.new(key, AES.MODE_CBC, iv)
    padded = pad(url_encoded.encode('utf-8'), AES.block_size)
    encrypted = cipher.encrypt(padded)
    # 4. Base64
    return base64.b64encode(encrypted).decode('utf-8')

def aes_decrypt(cipher_text: str, hash_key: str, hash_iv: str) -> dict:
    """對應 AesService::decrypt()"""
    # 1. Base64 decode
    encrypted = base64.b64decode(cipher_text)
    # 2. AES decrypt
    key = hash_key.encode('utf-8')[:16]
    iv = hash_iv.encode('utf-8')[:16]
    cipher = AES.new(key, AES.MODE_CBC, iv)
    decrypted = unpad(cipher.decrypt(encrypted), AES.block_size)
    # 3. URL decode
    url_decoded = unquote_plus(decrypted.decode('utf-8'))
    # 4. JSON decode
    return json.loads(url_decoded)
```

需要安裝：`pip install pycryptodome`

---

### Node.js

```javascript
const crypto = require('crypto');

function aesEncrypt(data, hashKey, hashIv) {
  // 1. JSON encode
  const jsonStr = JSON.stringify(data);
  // 2. URL encode（PHP urlencode 相容：空格→+，特殊字元需編碼）
  const urlEncoded = encodeURIComponent(jsonStr)
    .replace(/%20/g, '+')
    .replace(/~/g, '%7E')
    .replace(/!/g, '%21')
    .replace(/'/g, '%27')
    .replace(/\(/g, '%28')
    .replace(/\)/g, '%29')
    .replace(/\*/g, '%2A');
  // 3. AES-128-CBC + PKCS7（Node.js crypto 預設 PKCS7）
  const key = Buffer.from(hashKey, 'utf8').subarray(0, 16);
  const iv = Buffer.from(hashIv, 'utf8').subarray(0, 16);
  const cipher = crypto.createCipheriv('aes-128-cbc', key, iv);
  let encrypted = cipher.update(urlEncoded, 'utf8');
  encrypted = Buffer.concat([encrypted, cipher.final()]);
  // 4. Base64
  return encrypted.toString('base64');
}

function aesDecrypt(cipherText, hashKey, hashIv) {
  // 1. Base64 decode
  const encrypted = Buffer.from(cipherText, 'base64');
  // 2. AES decrypt
  const key = Buffer.from(hashKey, 'utf8').subarray(0, 16);
  const iv = Buffer.from(hashIv, 'utf8').subarray(0, 16);
  const decipher = crypto.createDecipheriv('aes-128-cbc', key, iv);
  let decrypted = decipher.update(encrypted);
  decrypted = Buffer.concat([decrypted, decipher.final()]);
  // 3. URL decode
  // 解密後的文字中 + 代表空格（加密時 encodeURIComponent 的 %20 被替換為 +）
  // 必須先還原 + → %20，才能用 decodeURIComponent 正確解碼
  const urlDecoded = decodeURIComponent(decrypted.toString('utf8').replace(/\+/g, '%20'));
  // 4. JSON decode
  return JSON.parse(urlDecoded);
}

module.exports = { aesEncrypt, aesDecrypt };
```

---

### TypeScript

```typescript
import crypto from 'crypto';

function aesEncrypt(data: Record<string, unknown>, hashKey: string, hashIv: string): string {
  // 1. JSON encode
  const jsonStr = JSON.stringify(data);
  // 2. URL encode（PHP urlencode 相容：空格→+，特殊字元需編碼）
  const urlEncoded = encodeURIComponent(jsonStr)
    .replace(/%20/g, '+')
    .replace(/~/g, '%7E')
    .replace(/!/g, '%21')
    .replace(/'/g, '%27')
    .replace(/\(/g, '%28')
    .replace(/\)/g, '%29')
    .replace(/\*/g, '%2A');
  // 3. AES-128-CBC + PKCS7（Node.js crypto 預設 PKCS7）
  const key = Buffer.from(hashKey, 'utf8').subarray(0, 16);
  const iv = Buffer.from(hashIv, 'utf8').subarray(0, 16);
  const cipher = crypto.createCipheriv('aes-128-cbc', key, iv);
  let encrypted = cipher.update(urlEncoded, 'utf8');
  encrypted = Buffer.concat([encrypted, cipher.final()]);
  // 4. Base64
  return encrypted.toString('base64');
}

function aesDecrypt(cipherText: string, hashKey: string, hashIv: string): Record<string, unknown> {
  // 1. Base64 decode
  const encrypted = Buffer.from(cipherText, 'base64');
  // 2. AES decrypt
  const key = Buffer.from(hashKey, 'utf8').subarray(0, 16);
  const iv = Buffer.from(hashIv, 'utf8').subarray(0, 16);
  const decipher = crypto.createDecipheriv('aes-128-cbc', key, iv);
  let decrypted = decipher.update(encrypted);
  decrypted = Buffer.concat([decrypted, decipher.final()]);
  // 3. URL decode（+ 代表空格，需先還原為 %20 才能正確 decode）
  const urlDecoded = decodeURIComponent(decrypted.toString('utf8').replace(/\+/g, '%20'));
  // 4. JSON decode
  return JSON.parse(urlDecoded);
}

export { aesEncrypt, aesDecrypt };
```

需要安裝：`npm install @types/node`（TypeScript 開發依賴）

---

> ⚠️ **全語言 JSON 序列化通用警告**
>
> AES 加密結果取決於 JSON 字串的精確位元內容。不同的 key 順序、空格、HTML 轉義都會產生不同的密文。
> 必須確保：(1) compact 格式（無多餘空格），(2) key 順序與 PHP `json_encode` 一致，(3) 不轉義 HTML 字元。
> 各語言的具體注意事項標註於對應區段。完整對照表見 [guides/23-multi-language-integration.md](./23-multi-language-integration.md)。

### Java

> ⚠️ **Java JSON 序列化必查清單**
>
> - [ ] 使用 `GsonBuilder().disableHtmlEscaping().create()` — 防止 `<>&` 被轉義為 `\u003c`
> - [ ] `LinkedHashMap` 保持宣告順序；`HashMap` 不保證順序；`TreeMap` 按字母序
> - [ ] Gson 預設將 `Long` 序列化為 `double`（`1` → `1.0`），需用 `setPrettyPrinting(false)` 或 `Long` explicit handling
>
> **驗證**：加密後與 [test-vectors/aes-encryption.json](../test-vectors/aes-encryption.json) 對比。

> **JSON 序列化注意**：Java 的 `HashMap` 不保證 key 順序，必須使用 `LinkedHashMap` 保序（`LinkedHashMap` 走訪順序穩定但略慢於 `HashMap`；此處必須保序，無替代方案）。
> 使用 `GsonBuilder().disableHtmlEscaping()` 避免 `<`, `>`, `&` 被轉義為 `\uXXXX`。

```java
import javax.crypto.Cipher;
import javax.crypto.spec.IvParameterSpec;
import javax.crypto.spec.SecretKeySpec;
import java.net.URLDecoder;
import java.net.URLEncoder;
import java.nio.charset.StandardCharsets;
import java.util.Base64;

public class EcpayAes {

    public static String encrypt(String jsonStr, String hashKey, String hashIv) throws Exception {
        // 2. URL encode
        // URLEncoder.encode 不編碼 !*，但 PHP urlencode 會編碼為 %21/%2A（其餘 ~'() 已被 URLEncoder 編碼，replace 為冪等保險）
        String urlEncoded = URLEncoder.encode(jsonStr, "UTF-8")
            .replace("!", "%21").replace("~", "%7E").replace("*", "%2A")
            .replace("'", "%27").replace("(", "%28").replace(")", "%29"); // 空格→+
        // Java 8 相容寫法（不使用 StandardCharsets）：
        // URLEncoder.encode(source, "UTF-8")
        // 3. AES-128-CBC（PKCS5 在 AES 上等同 PKCS7）
        byte[] key = hashKey.getBytes(StandardCharsets.UTF_8);
        byte[] iv = hashIv.getBytes(StandardCharsets.UTF_8);
        byte[] keyBytes = new byte[16];
        byte[] ivBytes = new byte[16];
        System.arraycopy(key, 0, keyBytes, 0, Math.min(key.length, 16));
        System.arraycopy(iv, 0, ivBytes, 0, Math.min(iv.length, 16));

        Cipher cipher = Cipher.getInstance("AES/CBC/PKCS5Padding");
        cipher.init(Cipher.ENCRYPT_MODE,
            new SecretKeySpec(keyBytes, "AES"),
            new IvParameterSpec(ivBytes));
        byte[] encrypted = cipher.doFinal(urlEncoded.getBytes(StandardCharsets.UTF_8));
        // 4. Base64
        return Base64.getEncoder().encodeToString(encrypted);
    }

    /** 便利方法：直接傳入 Map，自動 JSON 序列化 */
    public static String encrypt(java.util.Map<String, Object> data, String hashKey, String hashIv) throws Exception {
        // ⚠️ Gson 預設會轉義 HTML 字元（< → \u003c），需停用：
        // Maven 依賴：com.google.code.gson:gson:2.10+
        String jsonStr = new com.google.gson.GsonBuilder()
            .disableHtmlEscaping()
            .create()
            .toJson(data);
        return encrypt(jsonStr, hashKey, hashIv);
    }
    // ⚠️ 若使用 Jackson：確認未啟用 JsonGenerator.Feature.ESCAPE_NON_ASCII，
    // 否則中文字元會被轉義為 \uXXXX，與 PHP 的 json_encode 輸出不同。

    public static String decrypt(String cipherText, String hashKey, String hashIv) throws Exception {
        // 1. Base64 decode
        byte[] encrypted = Base64.getDecoder().decode(cipherText);
        // 2. AES decrypt
        byte[] key = hashKey.getBytes(StandardCharsets.UTF_8);
        byte[] iv = hashIv.getBytes(StandardCharsets.UTF_8);
        byte[] keyBytes = new byte[16];
        byte[] ivBytes = new byte[16];
        System.arraycopy(key, 0, keyBytes, 0, Math.min(key.length, 16));
        System.arraycopy(iv, 0, ivBytes, 0, Math.min(iv.length, 16));

        Cipher cipher = Cipher.getInstance("AES/CBC/PKCS5Padding");
        cipher.init(Cipher.DECRYPT_MODE,
            new SecretKeySpec(keyBytes, "AES"),
            new IvParameterSpec(ivBytes));
        byte[] decrypted = cipher.doFinal(encrypted);
        // 3. URL decode
        return URLDecoder.decode(new String(decrypted, StandardCharsets.UTF_8), "UTF-8");
        // 呼叫端再 JSON.parse
    }
}
```

---

### C#

> **JSON 序列化注意**：`System.Text.Json` 使用 class 屬性定義順序，預設即為 compact 格式。
> 若使用匿名型別或 `Dictionary`，注意 key 順序可能與預期不同。

```csharp
using System;
using System.IO;
using System.Net;
using System.Security.Cryptography;
using System.Text;

public static class EcpayAes
{
    public static string Encrypt(string jsonStr, string hashKey, string hashIv)
    {
        // 2. URL encode
        // ⚠️ 必須使用 WebUtility.UrlEncode（大寫 hex）而非 HttpUtility.UrlEncode（小寫 hex）
        // PHP urlencode 輸出大寫 hex（%7B），AES 無 toLowerCase 步驟，hex 大小寫影響密文
        string urlEncoded = WebUtility.UrlEncode(jsonStr)
            ?.Replace("%20", "+")     // 保險：WebUtility 已輸出 +，但 Uri.EscapeDataString 輸出 %20——防止未來誤換 encoder
            .Replace("~", "%7E").Replace("!", "%21").Replace("*", "%2A")
            .Replace("'", "%27").Replace("(", "%28").Replace(")", "%29") ?? "";
        // 3. AES-128-CBC + PKCS7
        using var aes = Aes.Create();
        aes.Key = Encoding.UTF8.GetBytes(hashKey)[..16];
        aes.IV = Encoding.UTF8.GetBytes(hashIv)[..16];
        aes.Mode = CipherMode.CBC;
        aes.Padding = PaddingMode.PKCS7;

        using var encryptor = aes.CreateEncryptor();
        byte[] plainBytes = Encoding.UTF8.GetBytes(urlEncoded);
        byte[] encrypted = encryptor.TransformFinalBlock(plainBytes, 0, plainBytes.Length);
        // 4. Base64
        return Convert.ToBase64String(encrypted);
    }

    public static string Decrypt(string cipherText, string hashKey, string hashIv)
    {
        // 1. Base64 decode
        byte[] encrypted = Convert.FromBase64String(cipherText);
        // 2. AES decrypt
        using var aes = Aes.Create();
        aes.Key = Encoding.UTF8.GetBytes(hashKey)[..16];
        aes.IV = Encoding.UTF8.GetBytes(hashIv)[..16];
        aes.Mode = CipherMode.CBC;
        aes.Padding = PaddingMode.PKCS7;

        using var decryptor = aes.CreateDecryptor();
        byte[] decrypted = decryptor.TransformFinalBlock(encrypted, 0, encrypted.Length);
        // 3. URL decode
        return WebUtility.UrlDecode(Encoding.UTF8.GetString(decrypted));
        // 呼叫端再 JSON.parse
    }
}
```

> **⚠️ 為何不用 HttpUtility.UrlEncode**：`HttpUtility.UrlEncode` 產生小寫 hex（如 `%7b`），而 PHP `urlencode` 產生大寫 hex（如 `%7B`）。
> CMV 流程有 `ToLower()` 步驟可消除此差異，但 AES 流程無此步驟，hex 大小寫直接影響密文結果。
> `WebUtility.UrlEncode` 位於 `System.Net`（所有 .NET 版本均可用），產生大寫 hex，空格→`+`（與 PHP 相同），需手動補 `~!*'()` 替換。

---

### Go

> ⚠️ **Go JSON 序列化必查清單**
>
> 在 AES 加密之前，確認你的 JSON 序列化設定：
> - [ ] 使用 `json.NewEncoder` + `SetEscapeHTML(false)` — 防止 `<>&` 被轉義為 `\u003c` 等
> - [ ] 使用 `struct` 定義 JSON 欄位順序（用 `json:"field"` tag）— `map[string]interface{}` 的 key 按字母序，可能與 PHP 順序不同
> - [ ] 若使用 `map[string]interface{}`：Go 按字母序排列 key，與 PHP 的自然宣告順序不同，會產生不同密文
>
> **驗證**：加密後與 [test-vectors/aes-encryption.json](../test-vectors/aes-encryption.json) 對比，確認密文一致。

> **JSON 序列化注意**：`json.Marshal` 預設會將 `<`, `>`, `&` 轉義為 `\u003c` 等 Unicode 跳脫序列。
> 必須使用 `json.NewEncoder` 搭配 `SetEscapeHTML(false)` 避免轉義。
> struct 欄位順序依定義順序（穩定），但 `map[string]interface{}` 會按字母序排列。

```go
package ecpay

import (
	"bytes"
	"crypto/aes"
	"crypto/cipher"
	"encoding/base64"
	"encoding/json"
	"fmt"
	"net/url"
	"strings"
)

// PKCS7 Padding（Go 標準庫不提供）
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
		return nil, fmt.Errorf("invalid padding: %d", padding)
	}
	for i := len(data) - padding; i < len(data); i++ {
		if data[i] != byte(padding) {
			return nil, fmt.Errorf("invalid PKCS7 padding")
		}
	}
	return data[:len(data)-padding], nil
}

func AesEncrypt(data interface{}, hashKey, hashIv string) (string, error) {
	// 1. JSON encode（禁止 HTML 轉義，與 PHP json_encode 一致）
	var buf bytes.Buffer
	encoder := json.NewEncoder(&buf)
	encoder.SetEscapeHTML(false)
	if err := encoder.Encode(data); err != nil {
		return "", err
	}
	jsonStr := strings.TrimRight(buf.String(), "\n")
	// 2. URL encode（空格→+）
	// QueryEscape 可能不編碼 ~（依 Go 版本），PHP urlencode 會；其餘 !*'() 多已被編碼，replace 為冪等保險
	urlEncoded := url.QueryEscape(jsonStr)
	r := strings.NewReplacer("~", "%7E", "!", "%21", "*", "%2A", "'", "%27", "(", "%28", ")", "%29")
	urlEncoded = r.Replace(urlEncoded)
	// 3. AES-128-CBC + PKCS7
	key := []byte(hashKey)[:16]
	iv := []byte(hashIv)[:16]
	block, err := aes.NewCipher(key)
	if err != nil {
		return "", err
	}
	padded := pkcs7Pad([]byte(urlEncoded), aes.BlockSize)
	encrypted := make([]byte, len(padded))
	mode := cipher.NewCBCEncrypter(block, iv)
	mode.CryptBlocks(encrypted, padded)
	// 4. Base64
	return base64.StdEncoding.EncodeToString(encrypted), nil
}

func AesDecrypt(cipherText, hashKey, hashIv string) (map[string]interface{}, error) {
	// 1. Base64 decode
	encrypted, err := base64.StdEncoding.DecodeString(cipherText)
	if err != nil {
		return nil, err
	}
	// 2. AES decrypt
	key := []byte(hashKey)[:16]
	iv := []byte(hashIv)[:16]
	block, err := aes.NewCipher(key)
	if err != nil {
		return nil, err
	}
	decrypted := make([]byte, len(encrypted))
	mode := cipher.NewCBCDecrypter(block, iv)
	mode.CryptBlocks(decrypted, encrypted)
	unpadded, err := pkcs7Unpad(decrypted)
	if err != nil {
		return nil, err
	}
	// 3. URL decode
	urlDecoded, err := url.QueryUnescape(string(unpadded))
	if err != nil {
		return nil, err
	}
	// 4. JSON decode
	var result map[string]interface{}
	err = json.Unmarshal([]byte(urlDecoded), &result)
	return result, err
}
```

---

### C

> **C/C++ 推薦庫**：OpenSSL EVP 介面（`EVP_aes_128_cbc()`）

> :lock: 此實作在 `free()` 前使用 `OPENSSL_cleanse()` 清除敏感資料，防止記憶體殘留。

> :warning: 本實作使用 OpenSSL EVP 介面。若您使用 OpenSSL 3.0+，請確認未使用已 deprecated 的低階 AES API（如 `AES_set_encrypt_key`）。

> ⚠️ 此實作依賴 guides/13 §C 的 `str_replace()` 輔助函式。完整可編譯程式碼需合併兩份檔案的 C 區段。
> 函式原型：`char* str_replace(const char *str, const char *from, const char *to);`（回傳 `malloc` 分配的新字串，呼叫端需 `free`）
>
> **完整編譯流程（最小可編譯範本）**：
>
> ```bash
> # 1. 安裝 OpenSSL 開發庫
> # Ubuntu/Debian: sudo apt install libssl-dev
> # macOS:         brew install openssl
> # Windows (vcpkg): vcpkg install openssl
>
> # 2. 合併檔案（按此順序）
> #    a. guides/13-checkmacvalue.md §C 的 str_replace() + ecpay_urlencode() + calc_check_mac_value()
> #    b. 本檔案 §C 的 aes_url_encode() + aes_encrypt() + aes_decrypt()
> #    c. 你的業務邏輯 main()
>
> # 3. 編譯指令
> gcc -o ecpay_client ecpay_client.c -lssl -lcrypto   # Linux/macOS
> # Windows (MSVC): cl ecpay_client.c /link /LIBPATH:"C:\vcpkg\installed\x64-windows\lib" libssl.lib libcrypto.lib
>
> # 4. 必要 #include（在程式碼頂部）
> # #include <openssl/evp.h>
> # #include <openssl/bio.h>
> # #include <openssl/buffer.h>
> # #include <string.h>
> # #include <stdlib.h>
> # #include <stdio.h>
> ```
>
> **⚠️ 記憶體管理注意**：C 實作中所有函式回傳 `malloc` 分配的字串，呼叫端必須 `free()`，否則記憶體洩漏。建議搭配 Valgrind 或 AddressSanitizer（`-fsanitize=address`）測試。

```c
#include <openssl/evp.h>
#include <openssl/bio.h>
#include <openssl/buffer.h>
#include <string.h>
#include <stdlib.h>
#include <curl/curl.h>

/* 編譯：gcc -o aes aes.c -lssl -lcrypto -lcurl
 * JSON 處理建議使用 cJSON: https://github.com/DaveGamble/cJSON */

/* Base64 encode using OpenSSL BIO */
static char* base64_encode(const unsigned char *input, int length) {
    BIO *bmem, *b64;
    BUF_MEM *bptr;
    b64 = BIO_new(BIO_f_base64());
    bmem = BIO_new(BIO_s_mem());
    b64 = BIO_push(b64, bmem);
    BIO_set_flags(b64, BIO_FLAGS_BASE64_NO_NL);
    BIO_write(b64, input, length);
    BIO_flush(b64);
    BIO_get_mem_ptr(b64, &bptr);
    char *result = malloc(bptr->length + 1);
    memcpy(result, bptr->data, bptr->length);
    result[bptr->length] = '\0';
    BIO_free_all(b64);
    return result;
}

/* Base64 decode */
static unsigned char* base64_decode(const char *input, int *out_len) {
    int len = strlen(input);
    unsigned char *result = malloc(len);
    BIO *b64 = BIO_new(BIO_f_base64());
    BIO *bmem = BIO_new_mem_buf(input, len);
    bmem = BIO_push(b64, bmem);
    BIO_set_flags(bmem, BIO_FLAGS_BASE64_NO_NL);
    *out_len = BIO_read(bmem, result, len);
    BIO_free_all(bmem);
    return result;
}

/* AES-128-CBC 加密（完整端到端：JSON → URL encode → AES → Base64） */
char* ecpay_aes_encrypt(const char* json_str, const char* hash_key, const char* hash_iv) {
    /* Step 1: URL encode */
    CURL *curl = curl_easy_init();
    char *url_encoded = curl_easy_escape(curl, json_str, 0);
    /* curl_easy_escape 空格→%20，PHP urlencode 空格→+，需替換 */
    char *temp;
    temp = str_replace(url_encoded, "%20", "+");   curl_free(url_encoded); url_encoded = temp;
    /* curl_easy_escape 不編碼 ~ (屬 RFC 3986 unreserved set)，但 PHP urlencode 會將其編碼為 %7E；
       !*'() 不在 RFC 3986 unreserved set，curl_easy_escape 已編碼；以下 str_replace 為冪等保險 */
    temp = str_replace(url_encoded, "~", "%7E");   free(url_encoded); url_encoded = temp;
    temp = str_replace(url_encoded, "!", "%21");   free(url_encoded); url_encoded = temp;
    temp = str_replace(url_encoded, "*", "%2A");   free(url_encoded); url_encoded = temp;
    temp = str_replace(url_encoded, "'", "%27");   free(url_encoded); url_encoded = temp;
    temp = str_replace(url_encoded, "(", "%28");   free(url_encoded); url_encoded = temp;
    temp = str_replace(url_encoded, ")", "%29");   free(url_encoded); url_encoded = temp;

    /* Step 2: 取前 16 bytes 作為 key/iv */
    unsigned char key[16], iv[16];
    memcpy(key, hash_key, 16);
    memcpy(iv, hash_iv, 16);

    /* Step 3: AES-128-CBC + PKCS7 加密 */
    EVP_CIPHER_CTX *ctx = EVP_CIPHER_CTX_new();
    EVP_EncryptInit_ex(ctx, EVP_aes_128_cbc(), NULL, key, iv);

    int len = strlen(url_encoded);
    int block_size = 16;
    int padded_len = len + (block_size - len % block_size);
    unsigned char *ciphertext = malloc(padded_len + block_size);
    int out_len = 0, final_len = 0;

    EVP_EncryptUpdate(ctx, ciphertext, &out_len, (unsigned char*)url_encoded, len);
    EVP_EncryptFinal_ex(ctx, ciphertext + out_len, &final_len);
    out_len += final_len;

    EVP_CIPHER_CTX_free(ctx);
    OPENSSL_cleanse(url_encoded, strlen(url_encoded));
    free(url_encoded);  /* url_encoded 指向 str_replace 的 malloc 記憶體，不可用 curl_free */
    curl_easy_cleanup(curl);

    /* Step 4: Base64 encode */
    BIO *b64 = BIO_new(BIO_f_base64());
    BIO *bmem = BIO_new(BIO_s_mem());
    b64 = BIO_push(b64, bmem);
    BIO_set_flags(b64, BIO_FLAGS_BASE64_NO_NL);
    BIO_write(b64, ciphertext, out_len);
    BIO_flush(b64);

    BUF_MEM *bptr;
    BIO_get_mem_ptr(b64, &bptr);
    char *result = malloc(bptr->length + 1);
    memcpy(result, bptr->data, bptr->length);
    result[bptr->length] = '\0';

    BIO_free_all(b64);
    free(ciphertext);

    return result; /* 呼叫者需 free() */
}

/* AES-128-CBC 解密（完整端到端：Base64 → AES → URL decode → JSON） */
char* ecpay_aes_decrypt(const char* cipher_text, const char* hash_key, const char* hash_iv) {
    /* Step 1: Base64 decode */
    int encrypted_len;
    unsigned char *encrypted = base64_decode(cipher_text, &encrypted_len);

    /* Step 2: 取前 16 bytes 作為 key/iv */
    unsigned char key[16], iv[16];
    memcpy(key, hash_key, 16);
    memcpy(iv, hash_iv, 16);

    /* Step 3: AES-128-CBC 解密 */
    EVP_CIPHER_CTX *ctx = EVP_CIPHER_CTX_new();
    EVP_DecryptInit_ex(ctx, EVP_aes_128_cbc(), NULL, key, iv);

    unsigned char *plaintext = malloc(encrypted_len + 16);
    int out_len = 0, final_len = 0;

    EVP_DecryptUpdate(ctx, plaintext, &out_len, encrypted, encrypted_len);
    EVP_DecryptFinal_ex(ctx, plaintext + out_len, &final_len);
    out_len += final_len;
    plaintext[out_len] = '\0';

    EVP_CIPHER_CTX_free(ctx);
    free(encrypted);

    /* Step 4: URL decode
       curl_easy_unescape 只解碼 %XX，不處理 + → space（與 PHP urldecode 不同）。
       ECPay aesUrlEncode 把空格編為 +，必須先將 + 換回 %20 再交給 curl_easy_unescape。 */
    /* Replace + with %20 before unescape */
    size_t tmp_len = out_len;
    char *plus_fixed = malloc(tmp_len * 3 + 1); /* worst case: each '+' → '%20' */
    size_t j = 0;
    for (size_t i = 0; i < tmp_len; i++) {
        if (((char*)plaintext)[i] == '+') {
            plus_fixed[j++] = '%'; plus_fixed[j++] = '2'; plus_fixed[j++] = '0';
        } else {
            plus_fixed[j++] = ((char*)plaintext)[i];
        }
    }
    plus_fixed[j] = '\0';

    CURL *curl = curl_easy_init();
    int decoded_len;
    char *url_decoded = curl_easy_unescape(curl, plus_fixed, (int)j, &decoded_len);
    free(plus_fixed);

    char *result = malloc(decoded_len + 1);
    memcpy(result, url_decoded, decoded_len);
    result[decoded_len] = '\0';

    curl_free(url_decoded);
    curl_easy_cleanup(curl);
    OPENSSL_cleanse(plaintext, out_len);
    free(plaintext);

    return result; /* 呼叫者需 free()，回傳 JSON 字串 */
}
```

---

### C++

> **C/C++ 推薦庫**：OpenSSL EVP 介面（`EVP_aes_128_cbc()`）

> :warning: 本實作使用 OpenSSL EVP 介面。若您使用 OpenSSL 3.0+，請確認未使用已 deprecated 的低階 AES API（如 `AES_set_encrypt_key`）。

```cpp
#include <openssl/evp.h>
#include <openssl/bio.h>
#include <openssl/buffer.h>
#include <string>
#include <vector>
#include <sstream>
#include <iomanip>
#include <stdexcept>

// 推薦使用 nlohmann/json 做 JSON 處理
// 編譯：g++ -o aes aes.cpp -lssl -lcrypto -std=c++17

// AES 專用 URL encode（PHP urlencode 相容：空格→+，白名單 alnum + -_.）
// ⚠️ 與 CMV 的 ecpayUrlEncode 不同，AES 不做 toLower 和 .NET 替換
std::string aesUrlEncode(const std::string& str) {
    std::ostringstream encoded;
    encoded.fill('0');
    encoded << std::hex << std::uppercase;
    for (char c : str) {
        if (isalnum(static_cast<unsigned char>(c))
            || c == '-' || c == '_' || c == '.') {
            encoded << c;
        } else if (c == ' ') {
            encoded << '+';
        } else {
            encoded << '%' << std::setw(2) << static_cast<int>(static_cast<unsigned char>(c));
        }
    }
    return encoded.str();
}

// AES-128-CBC 加密（完整端到端：JSON → URL encode → AES → Base64）
std::string ecpayAesEncrypt(const std::string& jsonStr,
                             const std::string& hashKey,
                             const std::string& hashIv) {
    // Step 1: URL encode
    std::string urlEncoded = aesUrlEncode(jsonStr);

    // Step 2: 取前 16 bytes
    std::string key = hashKey.substr(0, 16);
    std::string iv = hashIv.substr(0, 16);

    // Step 3: AES-128-CBC + PKCS7
    EVP_CIPHER_CTX* ctx = EVP_CIPHER_CTX_new();
    if (!ctx) throw std::runtime_error("Failed to create cipher context");

    EVP_EncryptInit_ex(ctx, EVP_aes_128_cbc(), nullptr,
                       reinterpret_cast<const unsigned char*>(key.c_str()),
                       reinterpret_cast<const unsigned char*>(iv.c_str()));

    std::vector<unsigned char> ciphertext(urlEncoded.size() + EVP_MAX_BLOCK_LENGTH);
    int outLen = 0, finalLen = 0;

    EVP_EncryptUpdate(ctx, ciphertext.data(), &outLen,
                      reinterpret_cast<const unsigned char*>(urlEncoded.c_str()),
                      urlEncoded.size());
    EVP_EncryptFinal_ex(ctx, ciphertext.data() + outLen, &finalLen);
    outLen += finalLen;
    EVP_CIPHER_CTX_free(ctx);

    // Step 4: Base64 encode
    BIO* b64 = BIO_new(BIO_f_base64());
    BIO* bmem = BIO_new(BIO_s_mem());
    b64 = BIO_push(b64, bmem);
    BIO_set_flags(b64, BIO_FLAGS_BASE64_NO_NL);
    BIO_write(b64, ciphertext.data(), outLen);
    BIO_flush(b64);

    BUF_MEM* bptr;
    BIO_get_mem_ptr(b64, &bptr);
    std::string result(bptr->data, bptr->length);
    BIO_free_all(b64);

    return result;
}

// AES-128-CBC 解密（完整端到端：Base64 → AES → URL decode → JSON 字串）
std::string ecpayAesDecrypt(const std::string& cipherText,
                             const std::string& hashKey,
                             const std::string& hashIv) {
    // Step 1: Base64 decode
    BIO* b64 = BIO_new(BIO_f_base64());
    BIO* bmem = BIO_new_mem_buf(cipherText.data(), cipherText.size());
    bmem = BIO_push(b64, bmem);
    BIO_set_flags(bmem, BIO_FLAGS_BASE64_NO_NL);

    std::vector<unsigned char> encrypted(cipherText.size());
    int encryptedLen = BIO_read(bmem, encrypted.data(), cipherText.size());
    BIO_free_all(bmem);

    // Step 2: 取前 16 bytes
    std::string key = hashKey.substr(0, 16);
    std::string iv = hashIv.substr(0, 16);

    // Step 3: AES-128-CBC 解密
    EVP_CIPHER_CTX* ctx = EVP_CIPHER_CTX_new();
    if (!ctx) throw std::runtime_error("Failed to create cipher context");

    EVP_DecryptInit_ex(ctx, EVP_aes_128_cbc(), nullptr,
                       reinterpret_cast<const unsigned char*>(key.c_str()),
                       reinterpret_cast<const unsigned char*>(iv.c_str()));

    std::vector<unsigned char> plaintext(encryptedLen + EVP_MAX_BLOCK_LENGTH);
    int outLen = 0, finalLen = 0;
    EVP_DecryptUpdate(ctx, plaintext.data(), &outLen, encrypted.data(), encryptedLen);
    EVP_DecryptFinal_ex(ctx, plaintext.data() + outLen, &finalLen);
    outLen += finalLen;
    EVP_CIPHER_CTX_free(ctx);

    // Step 4: URL decode（將 + 還原為空格，再解碼 %XX）
    std::string urlEncoded(reinterpret_cast<char*>(plaintext.data()), outLen);

    // URL decode 實作
    std::string decoded;
    decoded.reserve(urlEncoded.size());
    for (size_t i = 0; i < urlEncoded.size(); ++i) {
        if (urlEncoded[i] == '+') {
            decoded += ' ';
        } else if (urlEncoded[i] == '%' && i + 2 < urlEncoded.size()) {
            int hex = 0;
            std::istringstream iss(urlEncoded.substr(i + 1, 2));
            if (iss >> std::hex >> hex) {
                decoded += static_cast<char>(hex);
                i += 2;
            } else {
                decoded += urlEncoded[i];
            }
        } else {
            decoded += urlEncoded[i];
        }
    }
    return decoded;
}

/*
 * 使用範例：
 * nlohmann::json j = data;
 * std::string json_str = j.dump(); // compact JSON
 * std::string encrypted = ecpayAesEncrypt(json_str, hashKey, hashIv);
 *
 * std::string decrypted = ecpayAesDecrypt(encrypted, hashKey, hashIv);
 * // decrypted 已完成 URL decode，直接是 JSON 字串
 * auto data = nlohmann::json::parse(decrypted);
 */
```

---

### Rust

> **Rust 推薦庫**：`aes` + `cbc` + `pkcs7` (RustCrypto 生態)

> **JSON 序列化注意**：`serde_json` 使用 struct 欄位定義順序（穩定）。
> 若使用 `serde_json::Map`，key 會按字母序排列。
> 預設不轉義 HTML 字元，預設產生 compact JSON（不含多餘空格）。

```rust
use aes::Aes128;
use cbc::{Encryptor, Decryptor};
use cbc::cipher::{BlockEncryptMut, BlockDecryptMut, KeyIvInit};
use cipher::block_padding::Pkcs7;
use base64::{Engine as _, engine::general_purpose};
use urlencoding;

type Aes128CbcEnc = Encryptor<Aes128>;
type Aes128CbcDec = Decryptor<Aes128>;

// 範例使用 .unwrap() 簡化錯誤處理；生產環境建議回傳 Result<String, Box<dyn std::error::Error>> 並使用 ? 運算子
fn aes_encrypt(json_str: &str, hash_key: &str, hash_iv: &str) -> String {
    // 2. URL encode（urlencoding 空格→%20，需替換）
    let url_encoded = urlencoding::encode(json_str)
        .replace("%20", "+").replace("~", "%7E")
        .replace("!", "%21").replace("*", "%2A")
        .replace("'", "%27").replace("(", "%28").replace(")", "%29");
    // 3. AES-128-CBC + PKCS7
    let key = &hash_key.as_bytes()[..16];
    let iv = &hash_iv.as_bytes()[..16];
    let encryptor = Aes128CbcEnc::new_from_slices(key, iv).unwrap();
    let mut buf = vec![0u8; url_encoded.len() + 16]; // room for padding
    let plaintext = url_encoded.as_bytes();
    buf[..plaintext.len()].copy_from_slice(plaintext);
    let encrypted = encryptor.encrypt_padded_mut::<Pkcs7>(&mut buf, plaintext.len()).unwrap();
    // 4. Base64
    general_purpose::STANDARD.encode(encrypted)
}

fn aes_decrypt(cipher_text: &str, hash_key: &str, hash_iv: &str) -> String {
    let encrypted = general_purpose::STANDARD.decode(cipher_text).unwrap();
    let key = &hash_key.as_bytes()[..16];
    let iv = &hash_iv.as_bytes()[..16];
    let decryptor = Aes128CbcDec::new_from_slices(key, iv).unwrap();
    let mut buf = encrypted.clone();
    let decrypted = decryptor.decrypt_padded_mut::<Pkcs7>(&mut buf).unwrap();
    // URL decode（+ → 空格）
    let url_decoded = urlencoding::decode(
        &String::from_utf8_lossy(decrypted).replace("+", "%20")
    ).unwrap().into_owned();
    url_decoded // 呼叫端再 serde_json::from_str
}
```

需要 crates（建議鎖定版本以避免 API 變動）:

```toml
# Cargo.toml — AES 加密相關依賴
aes = "0.8"
cbc = "0.1"
cipher = "0.4"
base64 = "0.22"
urlencoding = "2"
serde_json = "1"
```

---

### Swift

> **Swift 推薦庫（依加密模式選擇）**：
> - **AES-128-CBC**（站內付 / 發票 / 物流 / 票證 / 電子收據預設模式）：**`CommonCrypto`**（內建，`CCCrypt`）。⚠️ CryptoKit **不支援** CBC 模式。
> - **AES-128-GCM**（電子收據 V3.0+ 選用模式）：**`CryptoKit` 的 `AES.GCM`**（iOS 13+ / macOS 10.15+ 原生支援，為首選；較 CommonCrypto 更安全易用）。完整實作見本文件 [§AES-GCM 模式](#aes-gcm-模式電子收據選用)。

> **為何 CBC 不用 CryptoKit？** CryptoKit（iOS 13+）不直接支援 AES-CBC with PKCS7 padding。
> CryptoKit 的 `AES.GCM` 僅支援 GCM 模式。因此 AES-CBC 加解密需使用 CommonCrypto（`CCCrypt`）。
> **但 GCM 模式反而首選 CryptoKit**：若電子收據切換為 AES-GCM，`CryptoKit.AES.GCM.SealedBox` 提供最簡潔的 Swift 原生 API，見本文件 [§AES-GCM 模式](#aes-gcm-模式電子收據選用)。

> **Swift ECPay 加密工具選擇（必看）**：
>
> | 用途 | 使用的 Framework | 原因 |
> |------|----------------|------|
> | **AES-128-CBC**（站內付/發票/物流/票證 / 電子收據預設）| `CommonCrypto`（`CCCrypt`） | ECPay 核心使用 AES-128-**CBC**；CryptoKit 不支援 CBC |
> | **AES-128-GCM**（電子收據 V3.0+ 選用）| `CryptoKit`（`AES.GCM`）| iOS 13+ 原生 AEAD 支援，較 CommonCrypto 更安全易用 |
> | **CheckMacValue SHA256**（AIO 金流驗簽）| `CryptoKit`（`SHA256.hash`）| CryptoKit 支援 SHA256，iOS 13+ 可用 |
> | **URL Encode**（兩者都需要） | Foundation `addingPercentEncoding` | 搭配自訂 CharacterSet，見下方 `aesUrlEncode` 實作 |
>
> 結論：Swift 專案通常需要同時 `import CommonCrypto`（CBC）與 `import CryptoKit`（SHA256 + 選用 GCM）。

> **⚠️ JSON key 順序警告**：AES 加密結果取決於 JSON 字串的精確內容，不同的 key 順序會產生不同的密文。
> 若使用 `JSONSerialization`，請至少加上 `.sortedKeys`；或使用 `Codable` + `JSONEncoder.outputFormatting = [.sortedKeys]`。

```swift
import Foundation
import CommonCrypto

// Xcode 專案設定：CommonCrypto 已內建

// 獨立 aesUrlEncode 函式（供測試驗證使用）
func aesUrlEncode(_ str: String) -> String {
    // AES 專用：白名單 alnum + -_.（~ 不在白名單中，addingPercentEncoding 已編碼，replace 為冪等保險）
    var allowed = CharacterSet.alphanumerics
    allowed.insert(charactersIn: "-_.")
    return (str.addingPercentEncoding(withAllowedCharacters: allowed) ?? str)
        .replacingOccurrences(of: "%20", with: "+")
        .replacingOccurrences(of: "~", with: "%7E")
        .replacingOccurrences(of: "!", with: "%21")
        .replacingOccurrences(of: "*", with: "%2A")
        .replacingOccurrences(of: "'", with: "%27")
        .replacingOccurrences(of: "(", with: "%28")
        .replacingOccurrences(of: ")", with: "%29")
}

func aesEncrypt(data: [String: Any], hashKey: String, hashIv: String) -> String? {
    // 1. JSON encode
    guard let jsonData = try? JSONSerialization.data(withJSONObject: data, options: [.sortedKeys]),
          let jsonStr = String(data: jsonData, encoding: .utf8) else { return nil }
    // 2. URL encode（空格→+）
    // AES 專用：白名單 alnum + -_.（~ 不在白名單中，已被 addingPercentEncoding 編碼為 %7E，下方 replace 為冪等保險）
    var allowed = CharacterSet.alphanumerics
    allowed.insert(charactersIn: "-_.")
    guard let urlEncoded = jsonStr.addingPercentEncoding(
        withAllowedCharacters: allowed
    )?.replacingOccurrences(of: "%20", with: "+")
      .replacingOccurrences(of: "~", with: "%7E") else { return nil }
    // 3. AES-128-CBC + PKCS7
    let keyBytes = Array(hashKey.utf8.prefix(16))
    let ivBytes = Array(hashIv.utf8.prefix(16))
    let plainBytes = Array(urlEncoded.utf8)
    let bufferSize = plainBytes.count + kCCBlockSizeAES128
    var ciphertext = [UInt8](repeating: 0, count: bufferSize)
    var numBytesEncrypted: size_t = 0

    let status = CCCrypt(CCOperation(kCCEncrypt), CCAlgorithm(kCCAlgorithmAES),
        CCOptions(kCCOptionPKCS7Padding),
        keyBytes, kCCKeySizeAES128, ivBytes,
        plainBytes, plainBytes.count,
        &ciphertext, bufferSize, &numBytesEncrypted)

    guard status == kCCSuccess else { return nil }
    // 4. Base64
    return Data(ciphertext.prefix(numBytesEncrypted)).base64EncodedString()
}

func aesDecrypt(cipherText: String, hashKey: String, hashIv: String) -> [String: Any]? {
    // 1. Base64 decode
    guard let encrypted = Data(base64Encoded: cipherText) else { return nil }
    // 2. AES decrypt
    let keyBytes = Array(hashKey.utf8.prefix(16))
    let ivBytes = Array(hashIv.utf8.prefix(16))
    let bufferSize = encrypted.count + kCCBlockSizeAES128
    var plaintext = [UInt8](repeating: 0, count: bufferSize)
    var numBytesDecrypted: size_t = 0

    let status = CCCrypt(CCOperation(kCCDecrypt), CCAlgorithm(kCCAlgorithmAES),
        CCOptions(kCCOptionPKCS7Padding),
        keyBytes, kCCKeySizeAES128, ivBytes,
        Array(encrypted), encrypted.count,
        &plaintext, bufferSize, &numBytesDecrypted)

    guard status == kCCSuccess,
          let urlEncoded = String(bytes: plaintext.prefix(numBytesDecrypted), encoding: .utf8) else { return nil }
    // 3. URL decode
    let urlDecoded = urlEncoded.replacingOccurrences(of: "+", with: "%20")
        .removingPercentEncoding ?? urlEncoded
    // 4. JSON decode
    guard let data = urlDecoded.data(using: .utf8) else { return nil }
    return try? JSONSerialization.jsonObject(with: data) as? [String: Any]
}
```

---

### Kotlin

> **JSON 序列化注意**：與 Java 相同，必須使用 `GsonBuilder().disableHtmlEscaping()` 停用 HTML 轉義。
> 使用 `linkedMapOf()` 取代 `hashMapOf()` 保證 key 插入順序。

```kotlin
import javax.crypto.Cipher
import javax.crypto.spec.IvParameterSpec
import javax.crypto.spec.SecretKeySpec
import java.net.URLDecoder
import java.net.URLEncoder
import java.nio.charset.StandardCharsets
import java.util.Base64

// Gradle: 不需額外依賴。JSON 處理建議用 com.google.code.gson:gson:2.10+

// 獨立 aesUrlEncode 函式（供測試驗證使用）
fun aesUrlEncode(source: String): String {
    // URLEncoder.encode 不編碼 !*，replace 為冪等保險；空格→+
    return URLEncoder.encode(source, StandardCharsets.UTF_8)
        .replace("!", "%21").replace("~", "%7E").replace("*", "%2A")
        .replace("'", "%27").replace("(", "%28").replace(")", "%29")
}

fun ecpayAesEncrypt(jsonStr: String, hashKey: String, hashIv: String): String {
    // 2. URL encode
    // URLEncoder.encode 不編碼 !*，但 PHP urlencode 會編碼為 %21/%2A（其餘 ~'() 已被 URLEncoder 編碼，replace 為冪等保險）
    val urlEncoded = URLEncoder.encode(jsonStr, StandardCharsets.UTF_8)
        .replace("!", "%21").replace("~", "%7E").replace("*", "%2A")
        .replace("'", "%27").replace("(", "%28").replace(")", "%29") // 空格→+
    // 3. AES-128-CBC（PKCS5 在 AES 上等同 PKCS7）
    val keyBytes = hashKey.toByteArray(StandardCharsets.UTF_8).copyOf(16)
    val ivBytes = hashIv.toByteArray(StandardCharsets.UTF_8).copyOf(16)
    val cipher = Cipher.getInstance("AES/CBC/PKCS5Padding")
    cipher.init(Cipher.ENCRYPT_MODE, SecretKeySpec(keyBytes, "AES"), IvParameterSpec(ivBytes))
    val encrypted = cipher.doFinal(urlEncoded.toByteArray(StandardCharsets.UTF_8))
    // 4. Base64
    return Base64.getEncoder().encodeToString(encrypted)
}

fun ecpayAesDecrypt(cipherText: String, hashKey: String, hashIv: String): String {
    // 1. Base64 decode
    val encrypted = Base64.getDecoder().decode(cipherText)
    // 2. AES decrypt
    val keyBytes = hashKey.toByteArray(StandardCharsets.UTF_8).copyOf(16)
    val ivBytes = hashIv.toByteArray(StandardCharsets.UTF_8).copyOf(16)
    val cipher = Cipher.getInstance("AES/CBC/PKCS5Padding")
    cipher.init(Cipher.DECRYPT_MODE, SecretKeySpec(keyBytes, "AES"), IvParameterSpec(ivBytes))
    val decrypted = cipher.doFinal(encrypted)
    // 3. URL decode
    return URLDecoder.decode(String(decrypted, StandardCharsets.UTF_8), StandardCharsets.UTF_8)
    // 呼叫端再用 Gson().fromJson() 解析
}
```

---

### Ruby

> **JSON 序列化注意**：Ruby Hash 自 1.9+ 保證插入順序（穩定）。
> 必須使用 `JSON.generate(data)` 產生 compact JSON，勿用 `JSON.pretty_generate`（會加入空格和換行）。

> **Ruby CGI.escape 注意**：`CGI.escape` 安全字元集為 `a-zA-Z0-9_.-`（Ruby 2.5+ 的 `~` 已加入安全字元集不再編碼，但舊版會編碼為 `%7E`；空格→`+`）。
> `!*'()` 均已由 `CGI.escape` 編碼（不在安全字元集內），所有 `.gsub` 為冪等安全措施，確保與 PHP `urlencode` 行為一致。

```ruby
require 'openssl'
require 'base64'
require 'json'
require 'cgi'

# Gemfile: 不需額外依賴，使用 Ruby 標準庫

def aes_encrypt(data, hash_key, hash_iv)
  # 1. JSON encode
  json_str = JSON.generate(data)
  # 2. URL encode（空格→+）
  # CGI.escape 將 ~ 編碼為 %7E（安全字元集為 a-zA-Z0-9_.-），以下 gsub 為冪等保險
  url_encoded = CGI.escape(json_str).gsub('~', '%7E')
      .gsub('!', '%21').gsub('*', '%2A')
      .gsub("'", '%27').gsub('(', '%28').gsub(')', '%29')
  # 3. AES-128-CBC + PKCS7（OpenSSL 預設 PKCS7）
  cipher = OpenSSL::Cipher::AES128.new(:CBC)
  cipher.encrypt
  cipher.key = hash_key[0, 16]
  cipher.iv = hash_iv[0, 16]
  encrypted = cipher.update(url_encoded) + cipher.final
  # 4. Base64
  Base64.strict_encode64(encrypted)
end

def aes_decrypt(cipher_text, hash_key, hash_iv)
  # 1. Base64 decode
  encrypted = Base64.strict_decode64(cipher_text)
  # 2. AES decrypt
  decipher = OpenSSL::Cipher::AES128.new(:CBC)
  decipher.decrypt
  decipher.key = hash_key[0, 16]
  decipher.iv = hash_iv[0, 16]
  decrypted = decipher.update(encrypted) + decipher.final
  # 3. URL decode
  url_decoded = CGI.unescape(decrypted)
  # 4. JSON decode
  JSON.parse(url_decoded)
end
```

---

## AES-GCM 模式（電子收據選用）

> **適用服務**：**電子收據**（V3.0+，預設 AES-CBC，可於綠界後台切換為 AES-GCM）。其他 AES-JSON 服務（站內付 2.0、發票、物流、票證）**仍使用 AES-CBC**，不支援 GCM。
>
> **何時使用 GCM？** 有資料完整性（Tag 驗證）需求、或後台已設定切換。GCM 為 AEAD（認證加密），理論上比 CBC 更安全。若特店後台為預設 CBC，使用本節 GCM 程式碼會收到解密失敗。

### 與 AES-CBC 對照

| 項目 | AES-CBC（預設）| AES-GCM（電子收據選用）|
|------|---------------|----------------------|
| 演算法 | AES-128-CBC + PKCS7 padding | AES-128-GCM |
| Key | `HashKey` 前 16 byte | `HashKey` 前 16 byte（**與 CBC 共用同一組**）|
| IV / Nonce | `HashIV` 前 16 byte（固定） | **自行產生 12 byte 英數字**（每次請求不同）|
| Padding | PKCS7（必需） | 無（GCM 不需要）|
| 認證標籤 | 無 | 16 byte GCM Tag |
| 輸出結構 | `Base64( AES-CBC(data) )` | `Base64( IV(12B) ‖ Ciphertext ‖ Tag(16B) )` |
| 解密失敗症狀 | Padding error | Tag 驗證失敗（資料被竄改/Key 錯）|

### 加密流程（GCM）

1. JSON → URL encode（與 CBC 完全相同的 `aesUrlEncode`）
2. 產生隨機 12 byte IV（英數字，每次請求不同）
3. AES-128-GCM encrypt，取得 Ciphertext 與 16 byte Tag
4. 按順序串接：`IV (12B) || Ciphertext || Tag (16B)`
5. Base64 encode（標準字母表 `+/=`）

### 解密流程（GCM）

1. Base64 decode
2. 切片：前 12 byte = IV；後 16 byte = Tag；中間 = Ciphertext
3. AES-128-GCM decrypt + Tag 驗證（若 Tag 不符，各語言 API 會 raise 例外）
4. URL decode
5. JSON parse

### 測試向量模式（固定 IV）

生產環境 IV 必須**隨機產生**（每次不同）。但 `test-vectors/aes-encryption.json` 中的 GCM 向量為了跨語言決定性驗證，使用**固定 IV**（`iv_hex: "112233445566778899aabbcc"`）。

**下方 12 語言實作統一採用「optional iv 參數」模式**（`iv=None` / `iv?: Buffer` / `iv: Option<&[u8; 12]>` 等），不需要額外的 `_with_iv` 函式。各語言如何傳入固定 IV 以比對測試向量的對照：

| 語言 | 生產呼叫（隨機 IV） | 測試呼叫（固定 IV） |
|------|-------------------|-------------------|
| Python | `aes_gcm_encrypt(data, HK)` | `aes_gcm_encrypt(data, HK, iv=bytes.fromhex(iv_hex))` |
| Node.js | `aesGcmEncrypt(data, HK)` | `aesGcmEncrypt(data, HK, Buffer.from(iv_hex, 'hex'))` |
| TypeScript | `aesGcmEncrypt(data, HK)` | `aesGcmEncrypt(data, HK, Buffer.from(iv_hex, 'hex'))` |
| Java | `encrypt(pt, HK, null)` | `encrypt(pt, HK, hexToBytes(iv_hex))` |
| C# | `Encrypt(data, HK)` | `Encrypt(data, HK, Convert.FromHexString(iv_hex))` |
| Go | `AesGcmEncrypt(data, HK, nil)` | `AesGcmEncrypt(data, HK, ivBytes)` |
| C | `aes_gcm_encrypt(pt, len, key, NULL, &n)` | `aes_gcm_encrypt(pt, len, key, iv_buf, &n)` |
| C++ | `encrypt(pt, HK)` | `encrypt(pt, HK, ivVector)` |
| Rust | `aes_gcm_encrypt(pt, HK, None)` | `aes_gcm_encrypt(pt, HK, Some(&iv_arr))` |
| Swift | `try encrypt(pt, HK)` | `try encrypt(pt, HK, iv: Data(hex: iv_hex))` |
| Kotlin | `encrypt(pt, HK)` | `encrypt(pt, HK, iv = hexToBytes(iv_hex))` |
| Ruby | `encrypt(data, HK)` | `encrypt(data, HK, [iv_hex].pack('H*'))` |

> **驗證方式**：測試呼叫產出的 Base64 應等於 `test-vectors/aes-encryption.json` 中對應向量的 `expected_base64`；產出的 Tag（hex）應等於 `expected_tag_hex`。
> 參考 `test-vectors/verify.py` / `verify-node.js` 等 5 個驗證器的 `mode == 'gcm'` 分支實作。

---

### Python（GCM）

```python
# 依賴：pycryptodome（與 CBC 共用）
import base64, urllib.parse, json, os
from Crypto.Cipher import AES

def aes_gcm_encrypt(data: dict, hash_key: str, iv: bytes = None) -> str:
    """生產：iv=None 隨機產生；測試：傳入固定 12 byte IV"""
    if iv is None:
        iv = os.urandom(12)  # 生產環境：隨機 12 byte IV
    assert len(iv) == 12, "GCM IV must be 12 bytes"
    url_encoded = urllib.parse.quote_plus(
        json.dumps(data, separators=(',', ':'), ensure_ascii=False)
    ).replace('~', '%7E')
    key = hash_key.encode('utf-8')[:16]
    cipher = AES.new(key, AES.MODE_GCM, nonce=iv)
    ct, tag = cipher.encrypt_and_digest(url_encoded.encode('utf-8'))
    return base64.b64encode(iv + ct + tag).decode('utf-8')

def aes_gcm_decrypt(b64: str, hash_key: str) -> dict:
    raw = base64.b64decode(b64)
    iv, ct, tag = raw[:12], raw[12:-16], raw[-16:]
    key = hash_key.encode('utf-8')[:16]
    cipher = AES.new(key, AES.MODE_GCM, nonce=iv)
    pt = cipher.decrypt_and_verify(ct, tag)  # 失敗時 raise ValueError
    return json.loads(urllib.parse.unquote_plus(pt.decode('utf-8')))
```

---

### Node.js（GCM）

```javascript
// 依賴：內建 crypto（與 CBC 共用）
const crypto = require('crypto');

function aesGcmEncrypt(data, hashKey, iv = null) {
  if (iv === null) iv = crypto.randomBytes(12);        // 生產：隨機 IV
  if (iv.length !== 12) throw new Error('GCM IV must be 12 bytes');
  // aesUrlEncode — 與 CBC 版本相同，補齊 encodeURIComponent 不編碼的字元
  const urlEncoded = encodeURIComponent(JSON.stringify(data))
    .replace(/~/g, '%7E')     // ⚠️ 缺此行：encodeURIComponent 不編碼 ~（CBC/GCM 皆需）
    .replace(/!/g, '%21').replace(/'/g, '%27').replace(/\(/g, '%28')
    .replace(/\)/g, '%29').replace(/\*/g, '%2A')
    .replace(/%20/g, '+');    // Node.js 空格為 %20，PHP 為 +
  const key = Buffer.from(hashKey, 'utf8').subarray(0, 16);
  const cipher = crypto.createCipheriv('aes-128-gcm', key, iv);
  const ct = Buffer.concat([cipher.update(urlEncoded, 'utf8'), cipher.final()]);
  const tag = cipher.getAuthTag();  // 16 bytes
  return Buffer.concat([iv, ct, tag]).toString('base64');
}

function aesGcmDecrypt(b64, hashKey) {
  const raw = Buffer.from(b64, 'base64');
  const iv  = raw.subarray(0, 12);
  const tag = raw.subarray(raw.length - 16);
  const ct  = raw.subarray(12, raw.length - 16);
  const key = Buffer.from(hashKey, 'utf8').subarray(0, 16);
  const decipher = crypto.createDecipheriv('aes-128-gcm', key, iv);
  decipher.setAuthTag(tag);
  const pt = Buffer.concat([decipher.update(ct), decipher.final()]);
  return JSON.parse(decodeURIComponent(pt.toString('utf8').replace(/\+/g, '%20')));
}
```

---

### TypeScript（GCM）

```typescript
// 依賴：內建 crypto（與 Node.js 版相同）
import * as crypto from 'crypto';

interface EcpayReceiptData {
  [key: string]: unknown;
}

function aesGcmEncrypt(data: EcpayReceiptData, hashKey: string, iv?: Buffer): string {
  const nonce = iv ?? crypto.randomBytes(12);
  if (nonce.length !== 12) throw new Error('GCM IV must be 12 bytes');
  // aesUrlEncode — 同 Node.js 版，補齊 encodeURIComponent 不編碼的字元
  const urlEncoded = encodeURIComponent(JSON.stringify(data))
    .replace(/~/g, '%7E')     // ⚠️ 必須：encodeURIComponent 不編碼 ~
    .replace(/!/g, '%21').replace(/'/g, '%27').replace(/\(/g, '%28')
    .replace(/\)/g, '%29').replace(/\*/g, '%2A')
    .replace(/%20/g, '+');
  const key = Buffer.from(hashKey, 'utf8').subarray(0, 16);
  const cipher = crypto.createCipheriv('aes-128-gcm', key, nonce);
  const ct = Buffer.concat([cipher.update(urlEncoded, 'utf8'), cipher.final()]);
  const tag = cipher.getAuthTag();
  return Buffer.concat([nonce, ct, tag]).toString('base64');
}

function aesGcmDecrypt(b64: string, hashKey: string): EcpayReceiptData {
  const raw = Buffer.from(b64, 'base64');
  const iv  = raw.subarray(0, 12);
  const tag = raw.subarray(raw.length - 16);
  const ct  = raw.subarray(12, raw.length - 16);
  const key = Buffer.from(hashKey, 'utf8').subarray(0, 16);
  const decipher = crypto.createDecipheriv('aes-128-gcm', key, iv);
  decipher.setAuthTag(tag);
  const pt = Buffer.concat([decipher.update(ct), decipher.final()]);
  return JSON.parse(decodeURIComponent(pt.toString('utf8').replace(/\+/g, '%20')));
}
```

---

### Java（GCM）

```java
// 依賴：內建 javax.crypto（與 CBC 共用）
import javax.crypto.Cipher;
import javax.crypto.spec.GCMParameterSpec;
import javax.crypto.spec.SecretKeySpec;
import java.nio.charset.StandardCharsets;
import java.security.SecureRandom;
import java.util.Arrays;
import java.util.Base64;

public class EcpayAesGcm {
    public static String encrypt(String plaintextJson, String hashKey, byte[] iv) throws Exception {
        if (iv == null) {
            iv = new byte[12];
            new SecureRandom().nextBytes(iv);  // 生產：隨機 IV
        }
        if (iv.length != 12) throw new IllegalArgumentException("GCM IV must be 12 bytes");

        // aesUrlEncode — Java URLEncoder 不編碼 *, !, ~，需手動補
        String urlEncoded = java.net.URLEncoder.encode(plaintextJson, StandardCharsets.UTF_8)
            .replace("*", "%2A").replace("!", "%21").replace("~", "%7E");

        byte[] keyBytes = hashKey.getBytes(StandardCharsets.UTF_8);
        SecretKeySpec keySpec = new SecretKeySpec(Arrays.copyOf(keyBytes, 16), "AES");
        GCMParameterSpec gcmSpec = new GCMParameterSpec(128, iv);  // 128-bit tag

        Cipher cipher = Cipher.getInstance("AES/GCM/NoPadding");
        cipher.init(Cipher.ENCRYPT_MODE, keySpec, gcmSpec);
        byte[] ctWithTag = cipher.doFinal(urlEncoded.getBytes(StandardCharsets.UTF_8));
        // Java doFinal 返回 ciphertext||tag，需前綴 IV
        byte[] combined = new byte[iv.length + ctWithTag.length];
        System.arraycopy(iv, 0, combined, 0, iv.length);
        System.arraycopy(ctWithTag, 0, combined, iv.length, ctWithTag.length);
        return Base64.getEncoder().encodeToString(combined);
    }

    public static String decrypt(String encryptedB64, String hashKey) throws Exception {
        byte[] raw = Base64.getDecoder().decode(encryptedB64);
        byte[] iv = Arrays.copyOfRange(raw, 0, 12);
        byte[] ctWithTag = Arrays.copyOfRange(raw, 12, raw.length);

        byte[] keyBytes = hashKey.getBytes(StandardCharsets.UTF_8);
        SecretKeySpec keySpec = new SecretKeySpec(Arrays.copyOf(keyBytes, 16), "AES");
        GCMParameterSpec gcmSpec = new GCMParameterSpec(128, iv);

        Cipher cipher = Cipher.getInstance("AES/GCM/NoPadding");
        cipher.init(Cipher.DECRYPT_MODE, keySpec, gcmSpec);
        byte[] pt = cipher.doFinal(ctWithTag);  // Tag 失敗會 raise AEADBadTagException
        return new String(pt, StandardCharsets.UTF_8);  // 呼叫端再 URLDecoder.decode
    }
}
```

---

### C#（GCM）

```csharp
// 依賴：System.Security.Cryptography.AesGcm（.NET Core 3.0+）
using System;
using System.Net;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;

public static class EcpayAesGcm
{
    public static string Encrypt(object data, string hashKey, byte[]? iv = null)
    {
        iv ??= RandomNumberGenerator.GetBytes(12);  // 生產：隨機 12 byte IV
        if (iv.Length != 12) throw new ArgumentException("GCM IV must be 12 bytes");

        string json = JsonSerializer.Serialize(data, new JsonSerializerOptions {
            Encoder = System.Text.Encodings.Web.JavaScriptEncoder.UnsafeRelaxedJsonEscaping
        });
        // aesUrlEncode — WebUtility.UrlEncode 不編碼 ~, !, *, '；需手動補齊以符合 PHP urlencode
        string urlEncoded = WebUtility.UrlEncode(json)
            .Replace("~", "%7E").Replace("!", "%21").Replace("*", "%2A")
            .Replace("'", "%27").Replace("(", "%28").Replace(")", "%29");

        byte[] key = Encoding.UTF8.GetBytes(hashKey)[..16];
        byte[] plaintext = Encoding.UTF8.GetBytes(urlEncoded);
        byte[] ciphertext = new byte[plaintext.Length];
        byte[] tag = new byte[16];

        using var aesGcm = new AesGcm(key, 16);
        aesGcm.Encrypt(iv, plaintext, ciphertext, tag);

        byte[] combined = new byte[iv.Length + ciphertext.Length + tag.Length];
        Buffer.BlockCopy(iv, 0, combined, 0, iv.Length);
        Buffer.BlockCopy(ciphertext, 0, combined, iv.Length, ciphertext.Length);
        Buffer.BlockCopy(tag, 0, combined, iv.Length + ciphertext.Length, tag.Length);
        return Convert.ToBase64String(combined);
    }

    public static T Decrypt<T>(string encryptedB64, string hashKey)
    {
        byte[] raw = Convert.FromBase64String(encryptedB64);
        byte[] iv = raw[..12];
        byte[] tag = raw[^16..];
        byte[] ciphertext = raw[12..^16];

        byte[] key = Encoding.UTF8.GetBytes(hashKey)[..16];
        byte[] plaintext = new byte[ciphertext.Length];

        using var aesGcm = new AesGcm(key, 16);
        aesGcm.Decrypt(iv, ciphertext, tag, plaintext);  // Tag 失敗 throw CryptographicException

        string urlDecoded = WebUtility.UrlDecode(Encoding.UTF8.GetString(plaintext));
        return JsonSerializer.Deserialize<T>(urlDecoded)!;
    }
}
```

---

### Go（GCM）

```go
// 依賴：內建 crypto/cipher（與 CBC 共用）
package ecpay

import (
    "crypto/aes"
    "crypto/cipher"
    "crypto/rand"
    "encoding/base64"
    "encoding/json"
    "errors"
    "net/url"
    "strings"
)

func AesGcmEncrypt(data map[string]interface{}, hashKey string, iv []byte) (string, error) {
    if iv == nil {
        iv = make([]byte, 12)
        if _, err := rand.Read(iv); err != nil {  // 生產：隨機 IV
            return "", err
        }
    }
    if len(iv) != 12 {
        return "", errors.New("GCM IV must be 12 bytes")
    }

    jsonBytes, _ := json.Marshal(data)
    // aesUrlEncode — Go url.QueryEscape 不編碼 ~，需手動補齊
    r := strings.NewReplacer("~", "%7E", "!", "%21", "*", "%2A", "'", "%27", "(", "%28", ")", "%29")
    urlEncoded := r.Replace(url.QueryEscape(string(jsonBytes)))  // QueryEscape 空格→+

    block, err := aes.NewCipher([]byte(hashKey)[:16])
    if err != nil {
        return "", err
    }
    gcm, err := cipher.NewGCMWithNonceSize(block, 12)
    if err != nil {
        return "", err
    }
    // Seal(dst, nonce, plaintext, aad)；dst=iv 讓結果 = IV || Ciphertext || Tag
    sealed := gcm.Seal(iv, iv, []byte(urlEncoded), nil)
    return base64.StdEncoding.EncodeToString(sealed), nil
}

func AesGcmDecrypt(encryptedB64, hashKey string) (map[string]interface{}, error) {
    raw, err := base64.StdEncoding.DecodeString(encryptedB64)
    if err != nil {
        return nil, err
    }
    if len(raw) < 12+16 {
        return nil, errors.New("ciphertext too short")
    }
    iv := raw[:12]
    ctTag := raw[12:]

    block, err := aes.NewCipher([]byte(hashKey)[:16])
    if err != nil {
        return nil, err
    }
    gcm, err := cipher.NewGCMWithNonceSize(block, 12)
    if err != nil {
        return nil, err
    }
    pt, err := gcm.Open(nil, iv, ctTag, nil)  // Tag 失敗 return error
    if err != nil {
        return nil, err
    }
    urlDecoded, _ := url.QueryUnescape(string(pt))
    var result map[string]interface{}
    json.Unmarshal([]byte(urlDecoded), &result)
    return result, nil
}
```

---

### C（GCM，OpenSSL EVP）

```c
// 依賴：OpenSSL 1.1.1+
// ⚠️ 呼叫端責任：plaintext 必須已完成 aesUrlEncode（見 CBC 章節 ecpay_url_encode() 實作）
//    本函式僅做 AES-GCM 加密步驟，不重複實作 URL encode（與 CBC 版設計一致）
#include <openssl/evp.h>
#include <openssl/rand.h>
#include <string.h>

// Returns Base64(IV || Ciphertext || Tag) on success, NULL on error.
// Caller must free the returned buffer.
char *aes_gcm_encrypt(const unsigned char *plaintext, int pt_len,
                      const unsigned char *key /*16B*/,
                      const unsigned char *iv /*12B, NULL=random*/,
                      int *out_len) {
    unsigned char iv_buf[12];
    if (iv == NULL) {
        if (RAND_bytes(iv_buf, 12) != 1) return NULL;
        iv = iv_buf;
    }

    EVP_CIPHER_CTX *ctx = EVP_CIPHER_CTX_new();
    if (!EVP_EncryptInit_ex(ctx, EVP_aes_128_gcm(), NULL, NULL, NULL)) goto err;
    if (!EVP_CIPHER_CTX_ctrl(ctx, EVP_CTRL_GCM_SET_IVLEN, 12, NULL)) goto err;
    if (!EVP_EncryptInit_ex(ctx, NULL, NULL, key, iv)) goto err;

    unsigned char *ct = malloc(pt_len + 16);
    int ct_len = 0, final_len = 0;
    if (!EVP_EncryptUpdate(ctx, ct, &ct_len, plaintext, pt_len)) goto err;
    if (!EVP_EncryptFinal_ex(ctx, ct + ct_len, &final_len)) goto err;
    ct_len += final_len;

    unsigned char tag[16];
    if (!EVP_CIPHER_CTX_ctrl(ctx, EVP_CTRL_GCM_GET_TAG, 16, tag)) goto err;
    EVP_CIPHER_CTX_free(ctx);

    // Concat IV (12) || Ciphertext || Tag (16), then Base64
    int combined_len = 12 + ct_len + 16;
    unsigned char *combined = malloc(combined_len);
    memcpy(combined, iv, 12);
    memcpy(combined + 12, ct, ct_len);
    memcpy(combined + 12 + ct_len, tag, 16);
    free(ct);

    // ... Base64 encode combined → return (use EVP_EncodeBlock or similar)
    // Base64 encode implementation omitted for brevity; see test-vectors/verify.py for reference
    *out_len = combined_len;
    return (char *)combined;  // caller must Base64-encode

err:
    EVP_CIPHER_CTX_free(ctx);
    return NULL;
}
```

> C 完整範例需配合 `scripts/SDK_PHP/src/Services/AesService.php` 做 Base64 編碼。OpenSSL EVP API 為 C/C++ 標準選擇，Linux / macOS / Windows（需 vcpkg/MSYS2）皆可用。

---

### C++（GCM）

```cpp
// 依賴：OpenSSL 1.1.1+，C++17
// ⚠️ 呼叫端責任：plaintext 必須已完成 aesUrlEncode（見 CBC 章節同名 helper）
//    encrypt() 第一參數為 urlencoded 字串，非原始 JSON
#include <openssl/evp.h>
#include <openssl/rand.h>
#include <string>
#include <vector>
#include <stdexcept>

class EcpayAesGcm {
public:
    static std::string encrypt(const std::string& plaintext,
                                const std::string& hashKey,
                                const std::vector<uint8_t>& iv = {}) {
        std::vector<uint8_t> nonce = iv;
        if (nonce.empty()) {
            nonce.resize(12);
            if (RAND_bytes(nonce.data(), 12) != 1)
                throw std::runtime_error("RAND_bytes failed");
        }
        if (nonce.size() != 12) throw std::invalid_argument("IV must be 12 bytes");

        std::vector<uint8_t> key(hashKey.begin(), hashKey.begin() + 16);
        EVP_CIPHER_CTX *ctx = EVP_CIPHER_CTX_new();
        EVP_EncryptInit_ex(ctx, EVP_aes_128_gcm(), nullptr, nullptr, nullptr);
        EVP_CIPHER_CTX_ctrl(ctx, EVP_CTRL_GCM_SET_IVLEN, 12, nullptr);
        EVP_EncryptInit_ex(ctx, nullptr, nullptr, key.data(), nonce.data());

        std::vector<uint8_t> ct(plaintext.size() + 16);
        int ct_len = 0, final_len = 0;
        EVP_EncryptUpdate(ctx, ct.data(), &ct_len,
                          reinterpret_cast<const uint8_t*>(plaintext.data()), plaintext.size());
        EVP_EncryptFinal_ex(ctx, ct.data() + ct_len, &final_len);
        ct.resize(ct_len + final_len);

        std::vector<uint8_t> tag(16);
        EVP_CIPHER_CTX_ctrl(ctx, EVP_CTRL_GCM_GET_TAG, 16, tag.data());
        EVP_CIPHER_CTX_free(ctx);

        std::vector<uint8_t> combined;
        combined.insert(combined.end(), nonce.begin(), nonce.end());
        combined.insert(combined.end(), ct.begin(), ct.end());
        combined.insert(combined.end(), tag.begin(), tag.end());
        return base64Encode(combined);  // assume helper exists
    }
    // decrypt() 對稱實作，EVP_DecryptInit_ex + EVP_CTRL_GCM_SET_TAG + EVP_DecryptFinal_ex
    // 若 Tag 驗證失敗，EVP_DecryptFinal_ex 回傳 0，視為解密錯誤
private:
    static std::string base64Encode(const std::vector<uint8_t>& data);  // 見 guides/14 CBC 章節
};
```

---

### Rust（GCM）

```rust
// 依賴（Cargo.toml）：
// aes-gcm = "0.10"     // GCM 需新 crate，不同於 CBC 的 aes + cbc
// base64 = "0.22"
// rand = "0.8"
// urlencoding = "2"    // 與 CBC 共用

use aes_gcm::{Aes128Gcm, KeyInit, Nonce, aead::{Aead, AeadCore, OsRng}};
use base64::{Engine as _, engine::general_purpose};

// aesUrlEncode — 與 CBC 版本相同；urlencoding crate 空格→%20，需補齊
pub fn aes_url_encode(s: &str) -> String {
    urlencoding::encode(s)
        .replace("%20", "+").replace("~", "%7E")
        .replace("!", "%21").replace("*", "%2A")
        .replace("'", "%27").replace("(", "%28").replace(")", "%29")
}

pub fn aes_gcm_encrypt_str(plaintext_json: &str, hash_key: &str, iv: Option<&[u8; 12]>) -> Result<String, String> {
    let url_encoded = aes_url_encode(plaintext_json);
    aes_gcm_encrypt(url_encoded.as_bytes(), hash_key, iv)
}

pub fn aes_gcm_encrypt(plaintext: &[u8], hash_key: &str, iv: Option<&[u8; 12]>) -> Result<String, String> {
    // ⚠️ 本 low-level 函式接收已 urlencoded 的 bytes；呼叫端應先用 aes_url_encode() 處理
    //    或直接使用 aes_gcm_encrypt_str() 進行完整流程
    let nonce = match iv {
        Some(iv_bytes) => *iv_bytes,
        None => {
            let mut buf = [0u8; 12];
            rand::RngCore::fill_bytes(&mut OsRng, &mut buf);  // 生產：隨機
            buf
        }
    };
    let key_bytes = &hash_key.as_bytes()[..16];
    let cipher = Aes128Gcm::new_from_slice(key_bytes).map_err(|e| e.to_string())?;
    let nonce_ref = Nonce::from_slice(&nonce);

    let ct_with_tag = cipher.encrypt(nonce_ref, plaintext).map_err(|e| e.to_string())?;

    // Concat IV (12) || Ciphertext+Tag
    let mut combined = Vec::with_capacity(12 + ct_with_tag.len());
    combined.extend_from_slice(&nonce);
    combined.extend_from_slice(&ct_with_tag);
    Ok(general_purpose::STANDARD.encode(combined))
}

pub fn aes_gcm_decrypt(b64: &str, hash_key: &str) -> Result<Vec<u8>, String> {
    let raw = general_purpose::STANDARD.decode(b64).map_err(|e| e.to_string())?;
    if raw.len() < 12 + 16 {
        return Err("ciphertext too short".into());
    }
    let (iv, ct_tag) = raw.split_at(12);
    let key_bytes = &hash_key.as_bytes()[..16];
    let cipher = Aes128Gcm::new_from_slice(key_bytes).map_err(|e| e.to_string())?;
    let nonce = Nonce::from_slice(iv);

    cipher.decrypt(nonce, ct_tag).map_err(|e| format!("GCM decrypt failed (tag verify): {}", e))
}
```

> ⚠️ **Rust crate 差異**：CBC 使用 `aes` + `cbc`，GCM 使用 `aes-gcm`（獨立 crate，雖同屬 RustCrypto 生態）。兩者可共存於同一 `Cargo.toml`。

---

### Swift（GCM — 首選 CryptoKit）

```swift
// 依賴：CryptoKit（iOS 13+ / macOS 10.15+，無需引入 CommonCrypto）
import Foundation
import CryptoKit

enum EcpayAesGcm {
    // aesUrlEncode — 與 CBC 版本相同；Swift addingPercentEncoding 白名單 + 手動 replace
    static func aesUrlEncode(_ str: String) -> String {
        var allowed = CharacterSet.alphanumerics
        allowed.insert(charactersIn: "-_.")
        return (str.addingPercentEncoding(withAllowedCharacters: allowed) ?? str)
            .replacingOccurrences(of: "%20", with: "+")
            .replacingOccurrences(of: "~", with: "%7E")
            .replacingOccurrences(of: "!", with: "%21")
            .replacingOccurrences(of: "*", with: "%2A")
            .replacingOccurrences(of: "'", with: "%27")
            .replacingOccurrences(of: "(", with: "%28")
            .replacingOccurrences(of: ")", with: "%29")
    }

    static func encrypt(plaintextJson: String, hashKey: String, iv: Data? = nil) throws -> String {
        // 1. aesUrlEncode（⚠️ 必須，ECPay 流程 JSON → urlencode → AES）
        let urlEncoded = aesUrlEncode(plaintextJson)

        let key = SymmetricKey(data: hashKey.data(using: .utf8)!.prefix(16))
        let nonce: AES.GCM.Nonce
        if let ivData = iv {
            guard ivData.count == 12 else { throw NSError(domain: "ecpay", code: 1, userInfo: [NSLocalizedDescriptionKey: "GCM IV must be 12 bytes"]) }
            nonce = try AES.GCM.Nonce(data: ivData)
        } else {
            nonce = AES.GCM.Nonce()  // 生產：CryptoKit 自動產生隨機 IV
        }

        // 2. AES-GCM seal（sealedBox.combined 原生即為 IV || Ciphertext || Tag）
        let sealedBox = try AES.GCM.seal(urlEncoded.data(using: .utf8)!, using: key, nonce: nonce)
        return sealedBox.combined!.base64EncodedString()
    }

    static func decrypt(base64: String, hashKey: String) throws -> String {
        let key = SymmetricKey(data: hashKey.data(using: .utf8)!.prefix(16))
        let combined = Data(base64Encoded: base64)!
        let sealedBox = try AES.GCM.SealedBox(combined: combined)
        let decrypted = try AES.GCM.open(sealedBox, using: key)  // Tag 失敗 throw
        let urlEncoded = String(data: decrypted, encoding: .utf8)!
        // 呼叫端再 .removingPercentEncoding 還原 JSON 字串
        return urlEncoded
    }
}
```

> ⚠️ **為何 Swift GCM 比 CBC 乾淨**：CryptoKit 的 `SealedBox.combined` 原生就是 `IV(12B) || Ciphertext || Tag(16B)` 格式，與 ECPay 要求一致，無需手動串接。相較之下 Swift CBC 需透過 CommonCrypto 的 C API（`CCCrypt`），程式碼複雜度明顯較高。但 **aesUrlEncode step 兩者都需要**，不可省略。

---

### Kotlin（GCM）

```kotlin
// 依賴：內建 javax.crypto（Android API 26+，JVM 1.7+）
import javax.crypto.Cipher
import javax.crypto.spec.GCMParameterSpec
import javax.crypto.spec.SecretKeySpec
import java.nio.charset.StandardCharsets
import java.security.SecureRandom
import java.util.Base64

object EcpayAesGcm {
    fun encrypt(plaintextJson: String, hashKey: String, iv: ByteArray? = null): String {
        val nonce = iv ?: ByteArray(12).also { SecureRandom().nextBytes(it) }
        require(nonce.size == 12) { "GCM IV must be 12 bytes" }

        // aesUrlEncode — URLEncoder 不編碼 ~, !, *，需手動補齊
        val urlEncoded = java.net.URLEncoder.encode(plaintextJson, "UTF-8")
            .replace("*", "%2A").replace("!", "%21").replace("~", "%7E")

        val keyBytes = hashKey.toByteArray(StandardCharsets.UTF_8).copyOf(16)
        val keySpec = SecretKeySpec(keyBytes, "AES")
        val gcmSpec = GCMParameterSpec(128, nonce)  // 128-bit tag

        val cipher = Cipher.getInstance("AES/GCM/NoPadding")
        cipher.init(Cipher.ENCRYPT_MODE, keySpec, gcmSpec)
        val ctWithTag = cipher.doFinal(urlEncoded.toByteArray(StandardCharsets.UTF_8))

        return Base64.getEncoder().encodeToString(nonce + ctWithTag)
    }

    fun decrypt(encryptedB64: String, hashKey: String): String {
        val raw = Base64.getDecoder().decode(encryptedB64)
        val iv = raw.copyOfRange(0, 12)
        val ctWithTag = raw.copyOfRange(12, raw.size)

        val keyBytes = hashKey.toByteArray(StandardCharsets.UTF_8).copyOf(16)
        val keySpec = SecretKeySpec(keyBytes, "AES")
        val gcmSpec = GCMParameterSpec(128, iv)

        val cipher = Cipher.getInstance("AES/GCM/NoPadding")
        cipher.init(Cipher.DECRYPT_MODE, keySpec, gcmSpec)
        val pt = cipher.doFinal(ctWithTag)  // Tag 失敗 throw AEADBadTagException
        return String(pt, StandardCharsets.UTF_8)
    }
}
```

---

### Ruby（GCM）

```ruby
# 依賴：內建 openssl（與 CBC 共用）
require 'openssl'
require 'base64'
require 'cgi'
require 'json'
require 'securerandom'

module EcpayAesGcm
  def self.encrypt(data, hash_key, iv = nil)
    iv ||= SecureRandom.random_bytes(12)  # 生產：隨機
    raise ArgumentError, "GCM IV must be 12 bytes" unless iv.bytesize == 12

    json_str = JSON.generate(data)
    # aesUrlEncode — CGI.escape 不編碼 ~, !, *，需手動補齊
    url_encoded = CGI.escape(json_str)
      .gsub('~', '%7E').gsub('!', '%21').gsub('*', '%2A')

    cipher = OpenSSL::Cipher.new('aes-128-gcm')
    cipher.encrypt
    cipher.key = hash_key.byteslice(0, 16)
    cipher.iv = iv
    cipher.auth_data = ''  # 無 AAD
    ciphertext = cipher.update(url_encoded) + cipher.final
    tag = cipher.auth_tag  # 預設 16 bytes

    Base64.strict_encode64(iv + ciphertext + tag)
  end

  def self.decrypt(encrypted_b64, hash_key)
    raw = Base64.strict_decode64(encrypted_b64)
    iv  = raw.byteslice(0, 12)
    tag = raw.byteslice(-16, 16)
    ct  = raw.byteslice(12, raw.bytesize - 28)

    decipher = OpenSSL::Cipher.new('aes-128-gcm')
    decipher.decrypt
    decipher.key = hash_key.byteslice(0, 16)
    decipher.iv = iv
    decipher.auth_tag = tag
    decipher.auth_data = ''
    pt = decipher.update(ct) + decipher.final  # Tag 失敗 raise OpenSSL::Cipher::CipherError
    JSON.parse(CGI.unescape(pt))
  end
end
```

---

## GCM 特有常見錯誤

1. **IV 長度 12 vs 16 byte 誤配**：AES-CBC 用 16 byte IV，AES-GCM 用 12 byte IV。誤用 16 會 raise 例外（大部分 API 直接拒絕）。
2. **固定 IV 於生產環境**：GCM 若相同 Key 用同 IV 加密不同內容，會洩露明文。測試向量用固定 IV 僅為決定性驗證，**生產環境必須每次請求產生隨機 IV**。
3. **Tag 驗證失敗當成 null 處理**：GCM API 在 Tag 錯誤時會 raise 例外（Python `ValueError`、Java `AEADBadTagException`、C# `CryptographicException`、Go `error`、Swift `CryptoKitError`），**不會回傳 null**。須用 try/catch 處理。
4. **IV/Tag 順序顛倒**：ECPay 規格為 `IV(12) || Ciphertext || Tag(16)`。某些語言預設輸出為 `Ciphertext || Tag`（無 IV 前綴），需手動拼接。
5. **Base64 URL-safe 字母表**：必須使用標準 Base64（`+/=`），不可用 URL-safe 版本（`-_`）；語言預設若為 URL-safe 需明確指定。
6. **PHP openssl `$tag` 方向**：加密時 `$tag` 是 by-reference 輸出，解密時是 by-value 輸入，兩者呼叫方式不同。見 [guides/25 §AES-GCM 模式](./25-receipt.md#aes-gcm-模式新加密選項)。
7. **AAD（Additional Authenticated Data）不使用**：ECPay 規格 AAD 為空。若誤加 AAD，Tag 驗證會失敗。各語言若預設有 AAD 需明確設為空。
8. **HashIV 誤用於 GCM**：GCM 不使用 HashIV（CBC 才用）；GCM 的 IV 完全自產。誤將 `HashIV[:12]` 當 GCM IV 會導致測試通過但實際交易被拒（固定 IV 洩露明文）。

---

## 測試向量

使用測試帳號驗證：

```
HashKey: ejCk326UnaZWKisg
HashIV:  q9jcZX8Ib9LM8wYk

明文 JSON: {"MerchantID":"2000132","BarCode":"/1234567"}
```

加密步驟：
1. JSON → `{"MerchantID":"2000132","BarCode":"/1234567"}`
2. URL encode → `%7B%22MerchantID%22%3A%222000132%22%2C%22BarCode%22%3A%22%2F1234567%22%7D`
3. AES-128-CBC encrypt → 二進位
4. Base64 → 密文字串

預期結果（Base64）：`XeEOdHpTRvxKEqs/JD9RSd16s7VtpyWVCN6AV44pKTW3DVa6yI7vKmjBRp2eulDhXoru/qBqFDBH3fEqlkMn3bbJfJBfGAq+v+SvttutYnc=`

預期中間結果：
- Step 2 URL encode 結果：`%7B%22MerchantID%22%3A%222000132%22%2C%22BarCode%22%3A%22%2F1234567%22%7D`
- Step 4 Base64 結果：`XeEOdHpTRvxKEqs/JD9RSd16s7VtpyWVCN6AV44pKTW3DVa6yI7vKmjBRp2eulDhXoru/qBqFDBH3fEqlkMn3bbJfJBfGAq+v+SvttutYnc=`

用你的實作跑一遍，加密結果必須等於上方預期值。因為 AES-CBC 在相同 Key/IV/明文下產生相同密文，你可以用任一語言的實作做交叉驗證。解密時用預期的 Base64 密文反推，確認回到原始 JSON。

各語言驗證範例（Python）：
```python
data = {"MerchantID": "2000132", "BarCode": "/1234567"}
encrypted = aes_encrypt(data, 'ejCk326UnaZWKisg', 'q9jcZX8Ib9LM8wYk')
print(f'加密結果: {encrypted}')
expected = 'XeEOdHpTRvxKEqs/JD9RSd16s7VtpyWVCN6AV44pKTW3DVa6yI7vKmjBRp2eulDhXoru/qBqFDBH3fEqlkMn3bbJfJBfGAq+v+SvttutYnc='
assert encrypted == expected, f'加密結果不一致！\n  預期: {expected}\n  實際: {encrypted}'

decrypted = aes_decrypt(encrypted, 'ejCk326UnaZWKisg', 'q9jcZX8Ib9LM8wYk')
assert decrypted == data, '解密結果不一致！'
print('驗證通過')
```

> **注意**：確保 JSON 序列化的 key 順序和格式（compact, 無空格）與 PHP 的 `json_encode` 一致，否則加密結果會不同。
> 上方預期值基於 `{"MerchantID":"2000132","BarCode":"/1234567"}` 這個確切的 JSON 字串（無空格、key 順序為 MerchantID 在前）。
> 若你的語言 JSON 序列化預設排序不同（如字母序排為 BarCode 在前），需手動調整順序以匹配。
>
> **字母序 JSON key 的預期密文**（BarCode 在前）：
> 明文 JSON: `{"BarCode":"/1234567","MerchantID":"2000132"}`
> Step 2 URL encode: `%7B%22BarCode%22%3A%22%2F1234567%22%2C%22MerchantID%22%3A%222000132%22%7D`
> Base64 密文: `r0JSyF9wVmywUav725b3rdJs3xp/ekrC/7PGb18zhKyXkPsamV9l4rPnBkaaraPcHtMSwrmSPP3wuS7b8g/aAKGs0iGiknpgpbdXKXvFrYM=`
> 使用 Go `map` / Java `HashMap` / Swift `JSONEncoder` 等字母序 JSON 的語言，應比對此預期值。

### 特殊字元測試向量

驗證 `!*'()~` 等特殊字元的 URL encode 正確性：

```
HashKey: ejCk326UnaZWKisg
HashIV:  q9jcZX8Ib9LM8wYk

明文 JSON: {"Name":"test!*'()~value"}
```

URL encode 預期結果：
- `!` → `%21`
- `*` → `%2A`
- `'` → `%27`
- `(` → `%28`
- `)` → `%29`
- `~` → `%7E`

> **常見問題語言**：Node.js/TypeScript 的 `encodeURIComponent` 不編碼 `!*'()`，Java/Kotlin 的 `URLEncoder.encode` 不編碼 `*`。務必手動補上 replace。

Step 2 URL encode：`%7B%22Name%22%3A%22test%21%2A%27%28%29%7Evalue%22%7D`
預期加密 Base64：`uvI4yrErM37XNQkXGAgRgBuDOiJoVs72Xn/rum9Ejl1DSna4HyLSoY7764PmhTR7JXb9jJWLSjCGcZEDeFiABg==`

> **關鍵驗證點**：如果你的 URL encode 實作讓 `!*'()~` 任何一個保持原字元不編碼，
> 加密結果將與 PHP 不同，導致 ECPay API 解密失敗。
> 常見問題語言：C++（自訂白名單）、Swift（CharacterSet 設定）。

### PKCS7 16-byte 邊界測試向量

驗證當 URL encode 後的明文長度為 AES block size（16 bytes）整數倍時，PKCS7 是否正確添加額外的 16-byte padding block：

```
HashKey: ejCk326UnaZWKisg
HashIV:  q9jcZX8Ib9LM8wYk

明文 JSON: {"N":"1234567890"}
```

URL encode 結果：`%7B%22N%22%3A%221234567890%22%7D`（長度 32 bytes = 2 個 AES block）

> **PKCS7 規則**：明文長度為 block size 整數倍時，必須額外填充完整的 16-byte block（`\x10` × 16），確保解密端正確識別 padding。若 padding 實作錯誤（未補滿 block），解密端會失敗。

預期加密 Base64：`gVwWJnIpl1m3ZDypcRAjiCctilYnQhHn4h8OzJP5IxQPov7HuysXX+jPONvrHS7Z`

### 進階測試向量（含中文與特殊字元）

以下測試向量涵蓋中文、HTML entity 和 ECPay 常見特殊字元，用於驗證多語言實作的正確性。

**測試資料**（HashKey: `ejCk326UnaZWKisg`, HashIV: `q9jcZX8Ib9LM8wYk`）：

#### 向量 1：中文商品名稱

```json
{
  "MerchantID": "2000132",
  "ItemName": "測試商品（含稅）",
  "TotalAmount": 100
}
```

- URL encode 後（aesUrlEncode）：`%7B%22MerchantID%22%3A%222000132%22%2C%22ItemName%22%3A%22%E6%B8%AC%E8%A9%A6%E5%95%86%E5%93%81%EF%BC%88%E5%90%AB%E7%A8%85%EF%BC%89%22%2C%22TotalAmount%22%3A100%7D`
- 預期加密 Base64：因 AES 使用 CBC 模式，結果為確定性輸出（相同 key/iv/plaintext = 相同密文）

> **驗證方式**：用你的語言加密上述 JSON（需 `ensure_ascii=False` / `SetEscapeHTML(false)` 等），再用 PHP 解密驗證結果一致。
> **注意**：PHP `json_encode` 預設將中文轉為 `\uXXXX` 形式（例如 `測` → `\u6e2c`），而上述 URL encode 結果使用 UTF-8 直接編碼（`ensure_ascii=False`）。ECPay 伺服器兩種格式均可接受，但若你的實作使用 `\uXXXX` 形式，加密結果將與上述不同——這是正常的。

#### 向量 2：ItemName 含 # 分隔符

```json
{
  "ItemName": "商品A 100 TWD x 1#商品B 200 TWD x 2",
  "SalesAmount": 500
}
```

> `#` 在 ECPay 的 ItemName 中是多商品分隔符。URL encode 時 `#` → `%23`。

#### 向量 3：特殊字元邊界（`<>&"'`）

```json
{
  "TradeDesc": "Tom & Jerry's <Shop>",
  "ItemName": "A&B \"Special\""
}
```

> **關鍵陷阱**：
> - Go 的 `json.Marshal` 預設會把 `<>&` 轉為 `\u003c\u0026\u003e` — 必須 `SetEscapeHTML(false)`
> - Java 的 `Gson` 預設會轉義 `<>&'` — 必須 `disableHtmlEscaping()`
> - Python 的 `json.dumps` 需要 `ensure_ascii=False`
> - 上述任何一個錯誤都會導致密文不一致，ECPay 端解密失敗

#### 向量 4：URL encode safe characters（`-_.`）

```json
{
  "CustomerEmail": "user@test-site.com",
  "ItemName": "item_v2.0-beta"
}
```

> **關鍵陷阱**：PHP `urlencode()` 不編碼 `-`、`_`、`.`（它們是 safe characters）。
> 各語言的 URL encode 函式必須保持一致行為，否則加密結果不同導致 ECPay 解密失敗。
> 此向量可偵測白名單遺漏問題（如 C++ 的 `isalnum` 不含這三個字元）。

> 💡 上述進階測試情境的預期輸出取決於實作細節（JSON key 順序、空白處理、中文 `\uXXXX` vs UTF-8 直接編碼），建議以 PHP SDK 為基準比對：先用 PHP SDK 加密相同輸入，再將結果作為其他語言的 expected value。

### 跨語言一致性驗證

> 若你的系統中有多個語言服務需要互相驗證 AES 加密結果，請使用以下測試向量確認互操作性。

用上方測試向量驗證：
1. **Python 加密 → Go 解密**：Python 的 `encrypt()` 輸出應能被 Go 的 `decrypt()` 正確還原
2. **Node.js 加密 → Java 解密**：同上原則
3. 若互相解密失敗，通常是 **PKCS7 Padding** 或 **Base64 編碼格式**不一致

快速診斷：比較各語言加密後的 Base64 長度——相同明文/金鑰的 Base64 長度必須完全相同。

## 常見錯誤

1. **URL encode 順序錯誤** — 必須先 URL encode 再 AES 加密（非常規）
2. **Key/IV 長度** — 必須是 16 bytes（AES-128），取 HashKey/HashIV 的前 16 bytes
3. **Padding 模式** — 必須是 PKCS7（Java 的 PKCS5 在 16-byte block 上等同 PKCS7）
4. **Node.js 空格處理** — `encodeURIComponent` 空格是 `%20`，ECPay 期望 `+`
5. **Go 沒有 PKCS7** — 標準庫不提供，必須手動實作
6. **JSON 序列化差異** — 確保 JSON 輸出沒有多餘空格（使用 compact 模式）
7. **Rust URL encode** — `urlencoding::encode` 空格是 `%20`，需替換為 `+`

## AES 安全注意事項

> ⚠️ **AES 安全注意事項**
> 1. 不要在日誌中記錄解密後的完整敏感資料（如信用卡資訊）
> 2. HashKey/HashIV 禁止硬編碼在原始碼中，使用環境變數
> 3. 解密失敗時不要回傳詳細錯誤訊息（防止 padding oracle 攻擊資訊洩漏）
> 4. 確保使用 TLS 1.2+ 傳輸加密後的資料
> 5. ECPay 的 AES-CBC 使用固定 IV（HashIV），這在密碼學上不理想（相同明文產生相同密文）。但因所有通訊已強制走 TLS，加上請求中的 Timestamp/RqID 提供了唯一性，實務上安全風險可控。不要嘗試自行修改 IV 為隨機值，否則 ECPay 無法解密。

## 相關文件

- PHP SDK 原始碼:`scripts/SDK_PHP/src/Services/AesService.php`(`encrypt()` @ line 91-110、`decrypt()` @ line 41-63、`encryptData()` @ line 120、`decryptData()` @ line 73)
- CheckMacValue：[guides/13-checkmacvalue.md](./13-checkmacvalue.md)
- ECPG 整合：[guides/02-payment-ecpg.md](./02-payment-ecpg.md)
- B2C 發票：[guides/04-invoice-b2c.md](./04-invoice-b2c.md)
- 機器可讀測試向量（CI/自動化測試用）：`test-vectors/aes-encryption.json`

## 官方規格參照

- 站內付 2.0 加密方式：`references/Payment/站內付2.0API技術文件Web.md` → §附錄 / 參數加密方式說明
- B2C 發票加密：`references/Invoice/B2C電子發票介接技術文件.md` → §附錄 / 參數加密方式說明
- 全方位物流 v2 加密：`references/Logistics/全方位物流API技術文件.md` → §附錄 / 參數加密方式說明
- ECTicket加密（另需 CMV 雙重驗證）：`references/Ecticket/ECTicketAPI技術文件.md` → §附錄 / 參數加密方式說明
- 全方位物流加密：`references/Logistics/全方位物流服務API技術文件.md` → §附錄 / 參數加密方式說明
