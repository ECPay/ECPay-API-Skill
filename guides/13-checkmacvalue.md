> 對應 ECPay API 版本 | 基於 PHP SDK ecpay/sdk | 最後更新：2026-03

<!-- AI Section Index（供 AI 部分讀取大檔案用，2026-03-23 校準）
Python: line 147-201 | Node.js: line 202-263 | TypeScript: line 264-331
Java: line 332-396 | C#: line 397-476 | Go: line 477-554
C: line 555-729 | C++: line 730-852 | Rust: line 853-933
Swift: line 934-1018 | Kotlin: line 1019-1079 | Ruby: line 1080-1130
Test vectors: line 1131-1241
CI/自動化驗證: test-vectors/checkmacvalue.json (8 vectors) + test-vectors/verify.py
-->

# CheckMacValue 完整解說

> 📌 **語言規範**：生成目標語言程式碼時，同時載入 `guides/lang-standards/{語言}.md`（命名慣例、型別定義、錯誤處理、HTTP 設定等），確保產出的程式碼為 idiomatic 且生產就緒。

**快速跳轉**: [Python](#python) | [Node.js](#nodejs) | [TypeScript](#typescript) | [Java](#java) | [C#](#c) | [Go](#go) | [C](#c-1) | [C++](#c-2) | [Rust](#rust) | [Swift](#swift) | [Kotlin](#kotlin) | [Ruby](#ruby)

## 概述

CheckMacValue 是 ECPay 用於驗證請求/回應完整性的檢查碼。用於 AIO 金流和國內物流。非 PHP 開發者需要自行實作此機制。

> 💡 **驗證你的實作**：完成後使用 [`test-vectors/checkmacvalue.json`](../test-vectors/checkmacvalue.json) 的 8 個測試向量驗證正確性，或執行 `python test-vectors/verify.py` 自動化驗證。

## 使用場景

| 服務 | Hash 方法 | 使用方式 |
|------|----------|---------|
| AIO 金流 | SHA256 | 送出請求時附加 / 接收通知時驗證 |
| 國內物流 | MD5 | 送出請求時附加 / 接收通知時驗證 |
| 電子票證 | SHA256 | 送出請求與回應都需附加（**公式與 AIO 完全不同**：`strtoupper(SHA256(URLEncode(HashKey + JSON + HashIV)))`，其中 `URLEncode = urlencode() 後接 strtolower()`、**不做 .NET 字元替換**；直接串接（無排序、無 `=` 分隔符），`strtoupper` 將 SHA256 輸出轉為大寫，見 [guides/09 §CheckMacValue 計算](./09-ecticket.md)） |
| ECPG / 發票 / 全方位物流 | **不使用**（例外見下方） | 改用 AES 加密 |

> ⚠️ **例外**：B2C 發票的 `AllowanceByCollegiate`（線上折讓）其 ReturnURL Callback 使用 **Form POST + CheckMacValue（MD5）**，是電子發票中唯一帶 CheckMacValue 的 API。計算公式與 AIO 相同但雜湊演算法為 MD5，詳見 [guides/04 §線上折讓](./04-invoice-b2c.md)。

## 計算流程

> 從 `scripts/SDK_PHP/src/Services/CheckMacValueService.php::generate()` 精確對應(line 76-85)

```
1. filter()                    — 移除參數中既有的 CheckMacValue (CheckMacValueService.php:79)
2. sort()                      — Key 不區分大小寫字典序排序 (strcasecmp,呼叫 ArrayService::sort)
3. toEncodeSourceString()      — "HashKey={key}&{k1=v1&k2=v2&...}&HashIV={iv}" (line 149-154)
4. UrlService::ecpayUrlEncode()— urlencode → 轉小寫 → .NET 特殊字元替換 (UrlService.php:13-48,見下方 §ECPay 專用 URL Encode)
5. generateHash()              — SHA256 或 MD5 (CheckMacValueService.php:98)
6. strtoupper()                — 轉大寫 (line 84)
```

> ⚠️ **空值參數處理**：CheckMacValue 計算時，空字串參數（`param=`）仍須納入排序與組合；完全未傳送的參數則不納入。兩者計算結果不同，務必確認 API 文件中標記為「選填」的參數在未使用時應省略（不傳）而非傳空值。

## ECPay 專用 URL Encode

> 從 `scripts/SDK_PHP/src/Services/UrlService.php::ecpayUrlEncode()` 精確對應(line 13-48,`$search`/`$replace` 陣列定義 7 個字元替換)

```
1. urlencode()     — 標準 URL 編碼（空格 → +）
2. strtolower()    — 全部轉小寫
3. .NET 特殊字元還原：
   %2d → -
   %5f → _
   %2e → .
   %21 → !
   %2a → *
   %28 → (
   %29 → )
```

> ⚠️ **特殊字元最終輸出**（跨語言最常見 Bug 來源，勿誤用 encodeURIComponent 或 %20）：
> - 空格（space） → 最終為 `+`（urlencode 輸出 `+`，後續步驟不改變；非 %20）
> - `~`（tilde） → PHP urlencode 輸出 `%7E`，strtolower 轉為 `%7e`，.NET 替換表中無此項 → 最終為 `%7e`（**不被還原**）
> - `'`（apostrophe） → PHP urlencode 輸出 `%27`，strtolower 保持 `%27`，.NET 替換表中無此項 → 最終為 `%27`（**不被還原**）

## PHP 開發者（使用 SDK 可跳過手動實作）

> ✅ **安全說明**：PHP SDK 官方 `CheckMacValueService::verify()` 已使用 `hash_equals()` 進行 timing-safe 比較。
> 若需手動操作，以 `$factory->create(CheckMacValueService::class)` 建立後，`generate()` 只接受 `$source` 陣列一個參數（Key/IV/方法由 Factory 注入）：
> ```php
> $calculated = $checkMacValue->generate($params);
> $isValid = hash_equals($calculated, $receivedCheckMacValue);
> ```

SDK 已自動處理 CheckMacValue：
- 送出請求：`AutoSubmitFormWithCmvService` / `PostWithCmv*` 自動附加
- 接收通知：`VerifiedArrayResponse` 自動驗證
- 手動操作：`$factory->create(CheckMacValueService::class)`

## 12 種語言完整實作

> 💡 **驗證提醒**：完成任何語言的實作後，務必使用 `test-vectors/checkmacvalue.json` 的 8 個測試向量驗證輸出。每個語言區段末尾的預期值必須與測試向量完全一致。執行 `python test-vectors/verify.py` 可一次驗證所有向量。

以下實作從 PHP SDK 原始碼精確翻譯。支援 Python、Node.js、TypeScript、Java、C#、Go、C、C++、Rust、Swift、Kotlin、Ruby，每種語言提供三個函式：
1. `ecpayUrlEncode` — ECPay 專用 URL 編碼
2. `generateCheckMacValue` — 產生 CheckMacValue
3. `verifyCheckMacValue` — 驗證 CheckMacValue

### 各語言 URL Encode 行為差異

| 語言 | URL Encode 函式 | 空格編碼 | `~` / `'` 編碼 | 需要額外處理 |
|------|----------------|---------|---------|-------------|
| PHP | `urlencode()` | + | %7E / %27 | 否（原生行為） |
| Python | `urllib.parse.quote_plus()` | + | ~ (不編碼) / %27 | **需替換 ~→%7e** |
| Java | `URLEncoder.encode(s, "UTF-8")` | + | %7E（多數 JVM）或 ~ (部分 JVM) / %27 | **需替換 ~→%7e（實作已有 toLowerCase + replace，兩者都處理）** |
| C# | `WebUtility.UrlEncode()` | + | ~ (不編碼) / %27 | **需替換 ~→%7e** |
| Node.js | `encodeURIComponent()` | **%20** | ~ ' (不編碼) | **需替換 %20→+、~→%7e 和 '→%27** |
| TypeScript | `encodeURIComponent()` | **%20** | ~ ' (不編碼) | **需替換 %20→+、~→%7e 和 '→%27**（與 Node.js 相同） |
| Go | `url.QueryEscape()` | + | ~ ' (不編碼) | **需替換 ~→%7e 和 '→%27** |
| C | `curl_easy_escape()` | **%20** | ~ (不編碼) | **需替換 %20→+ 和 ~→%7e** |
| C++ | Boost.URL 或手動 | 視實作 | 視實作 | **需替換 ~→%7e** |
| Rust | `urlencoding::encode()` | **%20** | ~ (不編碼) | **需替換 %20→+ 和 ~→%7e** |
| Swift | `addingPercentEncoding()` | **%20** | %7E (白名單排除) | **需替換 %20→+** |
| Kotlin | `URLEncoder.encode()` | + | %7E | **需替換 ~→%7e（保險起見）** |
| Ruby | `CGI.escape()` | + | ~ (不編碼) | **需替換 ~→%7e** |

---

> **⚠️ 重要：ecpayUrlEncode 僅用於 CheckMacValue（CMV-SHA256 / CMV-MD5）**
>
> 本檔定義的 `ecpayUrlEncode`（urlencode → 轉小寫 → .NET 替換）**專屬於 CheckMacValue 計算**。
> AES-JSON 協定（ECPG 線上金流、電子發票、全方位/跨境物流）使用完全不同的 `aesUrlEncode`（僅 urlencode，無小寫、無 .NET 替換），定義於 [guides/14](./14-aes-encryption.md)。
>
> **絕不混用兩者** — 這是跨語言整合最常見的 Bug 來源。
> 權威參考:`scripts/SDK_PHP/src/Services/UrlService.php:13-48` 定義 `ecpayUrlEncode()`(CMV 專用,含小寫轉換和 .NET 字元替換);AES 加密則直接使用 PHP 內建 `urlencode()`(見 `AesService.php:96`),不經過 UrlService。本文件中的 `aesUrlEncode` 為文件輔助命名,非 SDK 原生方法。

> **⚠️ 安全警告：Timing-Safe 比較**
>
> 驗證 CheckMacValue 時**必須**使用 timing-safe 比較函式，避免 timing attack。
> 直接使用 `==` 比較會洩漏字串長度和內容資訊。以下所有語言的 `verify` 函式
> 均已使用各語言的 timing-safe 比較實作。
>
> | 語言 | Timing-Safe 函式 |
> |------|----------------|
> | PHP | `hash_equals()` |
> | Python | `hmac.compare_digest()` |
> | Node.js | `crypto.timingSafeEqual()` |
> | TypeScript | `crypto.timingSafeEqual()` |
> | Go | `subtle.ConstantTimeCompare()` |
> | Java | `MessageDigest.isEqual()` |
> | Kotlin | `MessageDigest.isEqual()` |
> | C# | `CryptographicOperations.FixedTimeEquals()` |
> | C | `CRYPTO_memcmp()` **(OpenSSL)** |
> | C++ | `CRYPTO_memcmp()` **(OpenSSL)** |
> | Rust | `subtle::ConstantTimeEq` |
> | Ruby | `OpenSSL.secure_compare()` |
> | Swift | `HMAC<SHA256>.isValidAuthenticationCode()` |

> 💡 Node.js `crypto.timingSafeEqual(a, b)` 要求兩個 Buffer **長度相同**，否則拋出 `ERR_CRYPTO_TIMING_SAFE_EQUAL_LENGTH`。SHA256 hex 長度固定 64，MD5 為 32，通常不會觸發；但防禦性程式碼應先檢查長度。

### Python

> ⚠️ **Python 特有陷阱**
> - `quote_plus()` 不編碼 `~`，需手動替換 `~` → `%7e`（PHP `urlencode('~')` 輸出 `%7E`）

```python
import hashlib
import hmac
import urllib.parse

def ecpay_url_encode(source: str) -> str:
    """對應 UrlService::ecpayUrlEncode()"""
    encoded = urllib.parse.quote_plus(source)  # 空格→+
    encoded = encoded.replace('~', '%7E')  # PHP urlencode('~') → %7E
    encoded = encoded.lower()
    replacements = {
        '%2d': '-', '%5f': '_', '%2e': '.', '%21': '!',
        '%2a': '*', '%28': '(', '%29': ')',
    }
    for old, new in replacements.items():
        encoded = encoded.replace(old, new)
    return encoded

def generate_check_mac_value(
    params: dict, hash_key: str, hash_iv: str, method: str = 'sha256'
) -> str:
    """對應 CheckMacValueService::generate()"""
    # 1. 移除既有 CheckMacValue
    filtered = {k: v for k, v in params.items() if k != 'CheckMacValue'}
    # 2. Key 不區分大小寫排序
    sorted_params = sorted(filtered.items(), key=lambda x: x[0].lower())
    # 3. 組合字串
    param_str = '&'.join(f'{k}={v}' for k, v in sorted_params)
    raw = f'HashKey={hash_key}&{param_str}&HashIV={hash_iv}'
    # 4. ECPay URL encode
    encoded = ecpay_url_encode(raw)
    # 5. Hash
    if method == 'md5':
        hashed = hashlib.md5(encoded.encode('utf-8')).hexdigest()
    else:
        hashed = hashlib.sha256(encoded.encode('utf-8')).hexdigest()
    # 6. 轉大寫
    return hashed.upper()

def verify_check_mac_value(
    params: dict, hash_key: str, hash_iv: str, method: str = 'sha256'
) -> bool:
    """對應 CheckMacValueService::verify()"""
    received = params.get('CheckMacValue', '')
    calculated = generate_check_mac_value(params, hash_key, hash_iv, method)
    return hmac.compare_digest(received, calculated)
```

---

### Node.js

> ⚠️ **Node.js 特有陷阱**
> - `encodeURIComponent` 不編碼 `!'()*`，需額外替換 `%20→+`、`~→%7e`、`'→%27`
> - 不可直接用 `==` 比較，需用 `crypto.timingSafeEqual`（長度不同時須獨立判斷）

```javascript
const crypto = require('crypto');

function ecpayUrlEncode(source) {
  // encodeURIComponent 空格→%20，需替換為 +
  // encodeURIComponent 不編碼 ' 和 ~，但 PHP urlencode 會編碼為 %27 和 %7E
  // 此處使用小寫 %7e，因後續 toLowerCase() 會統一小寫，%7e 與 %7E 等價（CheckMacValue 專用）
  let encoded = encodeURIComponent(source).replace(/%20/g, '+').replace(/~/g, '%7e').replace(/'/g, '%27');
  encoded = encoded.toLowerCase();
  const replacements = {
    '%2d': '-', '%5f': '_', '%2e': '.', '%21': '!',
    '%2a': '*', '%28': '(', '%29': ')',
  };
  for (const [old, char] of Object.entries(replacements)) {
    encoded = encoded.split(old).join(char);
  }
  return encoded;
}

function generateCheckMacValue(params, hashKey, hashIv, method = 'sha256') {
  // 1. 移除既有 CheckMacValue
  const filtered = Object.fromEntries(
    Object.entries(params).filter(([k]) => k !== 'CheckMacValue')
  );
  // 2. Key 不區分大小寫排序
  const sorted = Object.keys(filtered)
    .sort((a, b) => a.toLowerCase().localeCompare(b.toLowerCase()));
  // 3. 組合字串
  const paramStr = sorted.map(k => `${k}=${filtered[k]}`).join('&');
  const raw = `HashKey=${hashKey}&${paramStr}&HashIV=${hashIv}`;
  // 4. ECPay URL encode
  const encoded = ecpayUrlEncode(raw);
  // 5. Hash
  const hash = crypto.createHash(method).update(encoded, 'utf8').digest('hex');
  // 6. 轉大寫
  return hash.toUpperCase();
}

function verifyCheckMacValue(params, hashKey, hashIv, method = 'sha256') {
  const received = params.CheckMacValue || '';
  const calculated = generateCheckMacValue(params, hashKey, hashIv, method);
  const a = Buffer.from(received);
  const b = Buffer.from(calculated);
  if (a.length !== b.length) return false;
  return crypto.timingSafeEqual(a, b);
}

module.exports = { ecpayUrlEncode, generateCheckMacValue, verifyCheckMacValue };

// === ESM 版本 ===
// export { ecpayUrlEncode, generateCheckMacValue, verifyCheckMacValue };

```

---

### TypeScript

> ⚠️ **TypeScript 特有陷阱**
> - 與 Node.js 相同：`encodeURIComponent` 不編碼 `!'()*`，需額外替換 `%20→+`、`~→%7e`、`'→%27`

```typescript
import crypto from 'crypto';

interface EcpayParams {
  [key: string]: string;
}

type HashMethod = 'sha256' | 'md5';

function ecpayUrlEncode(source: string): string {
  // encodeURIComponent 不編碼 ' 和 ~，但 PHP urlencode 會編碼為 %27 和 %7E
  let encoded = encodeURIComponent(source).replace(/%20/g, '+').replace(/~/g, '%7e').replace(/'/g, '%27');
  encoded = encoded.toLowerCase();
  const replacements: Record<string, string> = {
    '%2d': '-', '%5f': '_', '%2e': '.', '%21': '!',
    '%2a': '*', '%28': '(', '%29': ')',
  };
  for (const [old, char] of Object.entries(replacements)) {
    encoded = encoded.split(old).join(char);
  }
  return encoded;
}

function generateCheckMacValue(
  params: EcpayParams, hashKey: string, hashIv: string, method: HashMethod = 'sha256'
): string {
  // 1. 移除既有 CheckMacValue
  const filtered: EcpayParams = Object.fromEntries(
    Object.entries(params).filter(([k]) => k !== 'CheckMacValue')
  );
  // 2. Key 不區分大小寫排序
  const sorted = Object.keys(filtered)
    .sort((a, b) => a.toLowerCase().localeCompare(b.toLowerCase()));
  // 3. 組合字串
  const paramStr = sorted.map(k => `${k}=${filtered[k]}`).join('&');
  const raw = `HashKey=${hashKey}&${paramStr}&HashIV=${hashIv}`;
  // 4. ECPay URL encode
  const encoded = ecpayUrlEncode(raw);
  // 5. Hash
  const hash = crypto.createHash(method).update(encoded, 'utf8').digest('hex');
  // 6. 轉大寫
  return hash.toUpperCase();
}

function verifyCheckMacValue(
  params: EcpayParams, hashKey: string, hashIv: string, method: HashMethod = 'sha256'
): boolean {
  const received = params.CheckMacValue || '';
  const calculated = generateCheckMacValue(params, hashKey, hashIv, method);
  const a = Buffer.from(received);
  const b = Buffer.from(calculated);
  if (a.length !== b.length) return false;
  return crypto.timingSafeEqual(a, b);
}

export { ecpayUrlEncode, generateCheckMacValue, verifyCheckMacValue };
export type { EcpayParams, HashMethod };
```

需要安裝：`npm install @types/node`（TypeScript 開發依賴）

---

### Java

> ⚠️ **Java 特有陷阱**
> - `URLEncoder.encode` 將空格編碼為 `+`（與 PHP 一致），但部分 JVM 不編碼 `~`，需手動補 `~→%7e`
> - JDK 8 的 `URLEncoder.encode(s, "UTF-8")` 與 JDK 10+ 的 `URLEncoder.encode(s, StandardCharsets.UTF_8)` 行為相同，但後者在舊版本無法編譯

```java
import java.net.URLEncoder;
import java.nio.charset.StandardCharsets;
import java.security.MessageDigest;
import java.util.*;

public class EcpayCheckMacValue {

    public static String ecpayUrlEncode(String source) throws Exception {
        String encoded = URLEncoder.encode(source, StandardCharsets.UTF_8); // Java 10+
        // Java 8 相容寫法：URLEncoder.encode(source, "UTF-8")
        encoded = encoded.toLowerCase();
        return encoded
            .replace("%2d", "-").replace("%5f", "_").replace("%2e", ".")
            .replace("%21", "!").replace("%2a", "*")
            .replace("%28", "(").replace("%29", ")")
            .replace("~", "%7e");  // PHP urlencode('~') → %7E（大寫），toLowerCase 後為 %7e；若 JVM 不編碼 ~，此處補充替換
    }

    public static String generate(
        Map<String, String> params, String hashKey, String hashIv, String method
    ) throws Exception {
        // 1. 移除 CheckMacValue
        TreeMap<String, String> sorted = new TreeMap<>(String.CASE_INSENSITIVE_ORDER);
        params.forEach((k, v) -> {
            if (!"CheckMacValue".equals(k)) sorted.put(k, v);
        });
        // 3. 組合字串
        StringJoiner sj = new StringJoiner("&");
        sorted.forEach((k, v) -> sj.add(k + "=" + v));
        String raw = "HashKey=" + hashKey + "&" + sj + "&HashIV=" + hashIv;
        // 4. ECPay URL encode
        String encoded = ecpayUrlEncode(raw);
        // 5. Hash
        MessageDigest md = MessageDigest.getInstance(
            "md5".equals(method) ? "MD5" : "SHA-256"
        );
        byte[] digest = md.digest(encoded.getBytes(StandardCharsets.UTF_8));
        StringBuilder sb = new StringBuilder();
        for (byte b : digest) sb.append(String.format("%02x", b));
        // 6. 轉大寫
        return sb.toString().toUpperCase();
    }

    public static boolean verify(
        Map<String, String> params, String hashKey, String hashIv, String method
    ) throws Exception {
        String received = params.getOrDefault("CheckMacValue", "");
        String calculated = generate(params, hashKey, hashIv, method);
        return MessageDigest.isEqual(
            received.getBytes(StandardCharsets.UTF_8),
            calculated.getBytes(StandardCharsets.UTF_8)
        );
    }
}
```

---

### C#

> ⚠️ **C# 特有陷阱**
> - 必須使用 `System.Net.WebUtility.UrlEncode()`（大寫 hex，跨平台）；`HttpUtility.UrlEncode`（小寫 hex）已由 guides/14 及 guides/24 明確禁止
> - `WebUtility.UrlEncode` 空格編碼為 `+`（與 PHP urlencode 相同），程式碼中的 `.Replace("%20", "+")` 為保險措施
> - `WebUtility.UrlEncode` 不編碼 `~`，需手動補 `.Replace("~", "%7e")`
> - `WebUtility.UrlEncode` 在所有 .NET 版本均會將 `'` 編碼為 `%27`（無需額外處理）

```csharp
using System;
using System.Collections.Generic;
using System.Linq;
using System.Net;
using System.Security.Cryptography;
using System.Text;

public static class EcpayCheckMacValue
{
    public static string EcpayUrlEncode(string source)
    {
        string encoded = WebUtility.UrlEncode(source) // 空格→+（與 PHP urlencode 相同）
            .Replace("%20", "+"); // 保險：確保無 %20 殘留（WebUtility 空格已為 +）
        encoded = encoded.ToLower();
        return encoded
            .Replace("%2d", "-").Replace("%5f", "_").Replace("%2e", ".")
            .Replace("%21", "!").Replace("%2a", "*")
            .Replace("%28", "(").Replace("%29", ")")
            .Replace("~", "%7e");  // PHP urlencode('~') → %7E
    }

    public static string Generate(
        Dictionary<string, string> parameters, string hashKey, string hashIv,
        string method = "sha256")
    {
        // 1. 移除 CheckMacValue
        var filtered = parameters
            .Where(p => p.Key != "CheckMacValue")
            .OrderBy(p => p.Key, StringComparer.OrdinalIgnoreCase)
            .ToList();
        // 3. 組合字串
        string paramStr = string.Join("&", filtered.Select(p => $"{p.Key}={p.Value}"));
        string raw = $"HashKey={hashKey}&{paramStr}&HashIV={hashIv}";
        // 4. ECPay URL encode
        string encoded = EcpayUrlEncode(raw);
        // 5. Hash
        byte[] bytes = Encoding.UTF8.GetBytes(encoded);
        string hash;
        if (method == "md5")
        {
            using var md5 = MD5.Create();
            hash = BitConverter.ToString(md5.ComputeHash(bytes)).Replace("-", "");
        }
        else
        {
            using var sha256 = SHA256.Create();
            hash = BitConverter.ToString(sha256.ComputeHash(bytes)).Replace("-", "");
        }
        // 6. 轉大寫
        return hash.ToUpper();
    }

    public static bool Verify(
        Dictionary<string, string> parameters, string hashKey, string hashIv,
        string method = "sha256")
    {
        parameters.TryGetValue("CheckMacValue", out string received);
        string calculated = Generate(parameters, hashKey, hashIv, method);
        return CryptographicOperations.FixedTimeEquals(
            Encoding.UTF8.GetBytes(received ?? ""),
            Encoding.UTF8.GetBytes(calculated)
        );
    }
}
```

> **⚠️ 為何不用 HttpUtility.UrlEncode**：`HttpUtility.UrlEncode` 產生小寫 hex（如 `%2f`），而 PHP `urlencode` 及 `WebUtility.UrlEncode` 產生大寫 hex（如 `%2F`）。
> CMV 流程雖有 `.ToLower()` 步驟可消除此差異，但建議全面使用 `WebUtility.UrlEncode`（`System.Net`，所有 .NET 版本免額外套件）以保持與 AES 流程（guides/14）的一致性，並避免因版本差異造成的 `'`（單引號）編碼問題（`.NET Framework` 的 `HttpUtility.UrlEncode` 不編碼 `'`，WebUtility 無此問題）。

---

### Go

> ⚠️ **Go 特有陷阱**
> - `url.QueryEscape` 將空格編碼為 `+`（與 PHP 一致），但不編碼 `~`，需手動補 `~→%7e`

```go
package ecpay

import (
	"crypto/md5"
	"crypto/sha256"
	"crypto/subtle"
	"fmt"
	"net/url"
	"sort"
	"strings"
)

func EcpayURLEncode(source string) string {
	encoded := url.QueryEscape(source) // 空格→+
	encoded = strings.ToLower(encoded)
	replacer := strings.NewReplacer(
		"%2d", "-", "%5f", "_", "%2e", ".",
		"%21", "!", "%2a", "*", "%28", "(", "%29", ")",
	)
	encoded = replacer.Replace(encoded)
	encoded = strings.ReplaceAll(encoded, "~", "%7e") // PHP urlencode('~') → %7E
	encoded = strings.ReplaceAll(encoded, "'", "%27") // Go url.QueryEscape 不編碼 '，但 PHP urlencode 會
	return encoded
}

func GenerateCheckMacValue(
	params map[string]string, hashKey, hashIv, method string,
) string {
	// 1. 移除 CheckMacValue + 排序
	keys := make([]string, 0, len(params))
	for k := range params {
		if k != "CheckMacValue" {
			keys = append(keys, k)
		}
	}
	sort.SliceStable(keys, func(i, j int) bool {
		return strings.ToLower(keys[i]) < strings.ToLower(keys[j])
	})
	// 3. 組合字串
	parts := make([]string, len(keys))
	for i, k := range keys {
		parts[i] = k + "=" + params[k]
	}
	raw := fmt.Sprintf("HashKey=%s&%s&HashIV=%s", hashKey, strings.Join(parts, "&"), hashIv)
	// 4. ECPay URL encode
	encoded := EcpayURLEncode(raw)
	// 5. Hash
	var hash string
	if method == "md5" {
		h := md5.Sum([]byte(encoded))
		hash = fmt.Sprintf("%x", h)
	} else {
		h := sha256.Sum256([]byte(encoded))
		hash = fmt.Sprintf("%x", h)
	}
	// 6. 轉大寫
	return strings.ToUpper(hash)
}

func VerifyCheckMacValue(
	params map[string]string, hashKey, hashIv, method string,
) bool {
	received := params["CheckMacValue"]
	calculated := GenerateCheckMacValue(params, hashKey, hashIv, method)
	return subtle.ConstantTimeCompare([]byte(received), []byte(calculated)) == 1
}
```

> Go 標準庫已包含所有依賴，不需額外安裝套件。

---

### C

> ⚠️ **C 特有陷阱**
> - `curl_easy_escape()` 將空格編碼為 `%20`，需替換為 `+`
> - OpenSSL 3.0+ 已將 `SHA256()`/`MD5()` 標記為 deprecated，建議改用 EVP 介面

> **推薦庫**：OpenSSL 1.1+（`HMAC()` / `EVP` 介面）— 大多數 Linux 環境已預裝

```c
#include <openssl/sha.h>
#include <openssl/md5.h>
#include <openssl/crypto.h>
#include <curl/curl.h>
#include <string.h>
#include <ctype.h>
#include <stdio.h>
#include <stdlib.h>

/* 編譯：gcc -o cmv cmv.c -lssl -lcrypto -lcurl */

/* 字串替換輔助函式 */
static char* str_replace(const char *str, const char *from, const char *to) {
    size_t from_len = strlen(from), to_len = strlen(to);
    size_t count = 0;
    const char *p = str;
    while ((p = strstr(p, from)) != NULL) { count++; p += from_len; }

    size_t new_len = strlen(str) + count * (to_len - from_len);
    char *result = malloc(new_len + 1);
    if (!result) return NULL;  /* malloc 失敗防護 */
    char *dst = result;
    p = str;
    while (*p) {
        if (strncmp(p, from, from_len) == 0) {
            memcpy(dst, to, to_len);
            dst += to_len;
            p += from_len;
        } else {
            *dst++ = *p++;
        }
    }
    *dst = '\0';
    return result;
}

char* ecpay_url_encode(CURL *curl, const char *source) {
    char *escaped = curl_easy_escape(curl, source, 0);
    /* curl_easy_escape 空格→%20，需替換為 + */
    char *with_plus = str_replace(escaped, "%20", "+");
    curl_free(escaped);
    /* 轉小寫 */
    size_t len = strlen(with_plus);
    char *lower = malloc(len + 1);
    for (size_t i = 0; i <= len; i++) lower[i] = tolower(with_plus[i]);
    free(with_plus);

    /* .NET 特殊字元替換 */
    const char *from[] = {"%2d","%5f","%2e","%21","%2a","%28","%29"};
    const char *to[]   = {"-",  "_",  ".",  "!",  "*",  "(",  ")" };
    char *current = lower;
    for (int i = 0; i < 7; i++) {
        char *next = str_replace(current, from[i], to[i]);
        free(current);
        current = next;
    }
    /* PHP urlencode('~') → %7E */
    char *tilde_replaced = str_replace(current, "~", "%7e");
    free(current);
    return tilde_replaced; /* 呼叫者需 free() */
}

// ⚠️ OpenSSL 3.0+ 已將 SHA256()/MD5() 標記為 deprecated
// 建議改用 EVP 介面：EVP_DigestInit_ex / EVP_DigestUpdate / EVP_DigestFinal_ex
// 詳見 OpenSSL wiki: EVP Message Digests

void sha256_hex_upper(const char *input, char output[65]) {
    unsigned char hash[SHA256_DIGEST_LENGTH];
    SHA256((unsigned char*)input, strlen(input), hash);
    for (int i = 0; i < SHA256_DIGEST_LENGTH; i++)
        sprintf(output + i*2, "%02X", hash[i]);
    output[64] = '\0';
}

void md5_hex_upper(const char *input, char output[33]) {
    unsigned char hash[MD5_DIGEST_LENGTH];
    MD5((unsigned char*)input, strlen(input), hash);
    for (int i = 0; i < MD5_DIGEST_LENGTH; i++)
        sprintf(output + i*2, "%02X", hash[i]);
    output[32] = '\0';
}

/* Key 不區分大小寫比較（用於 qsort）
   注意：strcasecmp 為 POSIX 函式，Windows MSVC 環境請改用 _stricmp */
#ifdef _WIN32
#define strcasecmp _stricmp
#endif
typedef struct { char *key; char *value; } KV;
static int kv_cmp(const void *a, const void *b) {
    return strcasecmp(((KV*)a)->key, ((KV*)b)->key);
}

char* generate_check_mac_value(
    KV *params, int count,
    const char *hash_key, const char *hash_iv,
    const char *method
) {
    /* 1. 過濾 CheckMacValue + 排序 */
    KV *filtered = malloc(sizeof(KV) * count);
    int n = 0;
    for (int i = 0; i < count; i++) {
        if (strcasecmp(params[i].key, "CheckMacValue") != 0)
            filtered[n++] = params[i];
    }
    qsort(filtered, n, sizeof(KV), kv_cmp);

    /* 2-3. 組合字串（動態計算所需 buffer 大小，避免溢出） */
    size_t buf_size = strlen(hash_key) + strlen(hash_iv) + 32; /* HashKey=...&...&HashIV=...\0 */
    for (int i = 0; i < n; i++) {
        buf_size += strlen(filtered[i].key) + strlen(filtered[i].value) + 2; /* key=val& */
    }
    char *raw = malloc(buf_size);
    if (!raw) { free(filtered); return NULL; }
    int pos = snprintf(raw, buf_size, "HashKey=%s&", hash_key);
    for (int i = 0; i < n; i++) {
        pos += snprintf(raw + pos, buf_size - pos, "%s=%s%s",
            filtered[i].key, filtered[i].value, (i < n-1) ? "&" : "");
    }
    snprintf(raw + pos, buf_size - pos, "&HashIV=%s", hash_iv);
    free(filtered);

    /* 4. ECPay URL encode */
   /* curl_easy_init() 在資源不足時可能回傳 NULL */
    CURL *curl = curl_easy_init();
    if (!curl) { free(raw); return NULL; }
    char *encoded = ecpay_url_encode(curl, raw);
    free(raw);
    curl_easy_cleanup(curl);

    /* 5-6. Hash → 大寫 */
    char *result = malloc(65);
    if (strcmp(method, "md5") == 0) {
        md5_hex_upper(encoded, result);
    } else {
        sha256_hex_upper(encoded, result);
    }
    free(encoded);
    return result; /* 呼叫者需 free() */
}

int verify_check_mac_value(
    KV *params, int count,
    const char *hash_key, const char *hash_iv,
    const char *method
) {
    /* 找到 CheckMacValue */
    const char *received = NULL;
    for (int i = 0; i < count; i++) {
        if (strcasecmp(params[i].key, "CheckMacValue") == 0) {
            received = params[i].value;
            break;
        }
    }
    if (!received) return 0;
    char *calculated = generate_check_mac_value(params, count, hash_key, hash_iv, method);
    if (!calculated) return 0;
    /* Timing-safe 比較 */
    int result = (strlen(received) == strlen(calculated)) &&
                 (CRYPTO_memcmp(received, calculated, strlen(calculated)) == 0);
    free(calculated);
    return result;
}
```

---

### C++

> ⚠️ **C++ 特有陷阱**
> - 手動 URL encode 時需確認 `~` 被編碼為 `%7e`（直接輸出不編碼的話結果錯誤）
> - OpenSSL 3.0+ 已將 `SHA256()`/`MD5()` 標記為 deprecated，建議改用 EVP 介面

> **推薦庫**：OpenSSL 1.1+（`HMAC()` / `EVP` 介面）— 大多數 Linux 環境已預裝

```cpp
#include <openssl/sha.h>
#include <openssl/md5.h>
#include <openssl/crypto.h>
#include <algorithm>
#include <map>
#include <string>
#include <sstream>
#include <iomanip>
#include <cctype>

// URL encode（手動實作，空格→+）
// 注意：PHP urlencode() 不會編碼 -_. (這些是安全字元)；.NET 替換規則 (%2d→- %5f→_ %2e→.) 對 PHP 而言是無效操作，但其他語言的 URL encoder 可能會編碼這些字元，替換規則保持跨語言相容性
// 此處直接輸出這些字元，最終結果相同但中間路徑不同
std::string urlEncode(const std::string &value) {
    std::ostringstream escaped;
    for (char c : value) {
        unsigned char uc = static_cast<unsigned char>(c);
        if (isalnum(uc) || c == '-' || c == '_' || c == '.') {
            escaped << c;
        } else if (c == ' ') {
            escaped << '+';
        } else {
            escaped << '%' << std::uppercase << std::hex
                    << std::setw(2) << std::setfill('0') << (int)uc;
        }
    }
    return escaped.str();
}

std::string ecpayUrlEncode(const std::string &source) {
    std::string encoded = urlEncode(source);
    // 轉小寫
    std::transform(encoded.begin(), encoded.end(), encoded.begin(), ::tolower);
    // .NET 替換
    auto replace = [&](const std::string &from, const std::string &to) {
        size_t pos = 0;
        while ((pos = encoded.find(from, pos)) != std::string::npos) {
            encoded.replace(pos, from.length(), to);
            pos += to.length();
        }
    };
    replace("%2d", "-"); replace("%5f", "_"); replace("%2e", ".");
    replace("%21", "!"); replace("%2a", "*"); replace("%28", "("); replace("%29", ")");
    replace("~", "%7e");  // PHP urlencode('~') → %7E
    return encoded;
}

std::string generateCheckMacValue(
    const std::map<std::string, std::string> &params,
    const std::string &hashKey, const std::string &hashIv,
    const std::string &method = "sha256"
) {
    // 不區分大小寫排序（std::map 搭配自訂 comparator）
    struct CaseInsensitive {
        bool operator()(const std::string &a, const std::string &b) const {
            std::string la = a, lb = b;
            std::transform(la.begin(), la.end(), la.begin(), ::tolower);
            std::transform(lb.begin(), lb.end(), lb.begin(), ::tolower);
            return la < lb;
        }
    };
    std::map<std::string, std::string, CaseInsensitive> sorted;
    for (const auto &[k, v] : params) {
        if (k != "CheckMacValue") sorted[k] = v;
    }
    // 組合
    std::ostringstream ss;
    ss << "HashKey=" << hashKey << "&";
    bool first = true;
    for (const auto &[k, v] : sorted) {
        if (!first) ss << "&";
        ss << k << "=" << v;
        first = false;
    }
    ss << "&HashIV=" << hashIv;
    // ECPay URL encode
    std::string encoded = ecpayUrlEncode(ss.str());
    // Hash
    // ⚠️ OpenSSL 3.0+ 已將 SHA256()/MD5() 標記為 deprecated
    // 建議改用 EVP 介面：EVP_DigestInit_ex / EVP_DigestUpdate / EVP_DigestFinal_ex
    // 詳見 OpenSSL wiki: EVP Message Digests
    if (method == "md5") {
        unsigned char hash[MD5_DIGEST_LENGTH];
        MD5((unsigned char*)encoded.c_str(), encoded.size(), hash);
        std::ostringstream hex;
        for (int i = 0; i < MD5_DIGEST_LENGTH; i++)
            hex << std::uppercase << std::hex << std::setw(2) << std::setfill('0') << (int)hash[i];
        return hex.str();
    } else {
        unsigned char hash[SHA256_DIGEST_LENGTH];
        SHA256((unsigned char*)encoded.c_str(), encoded.size(), hash);
        std::ostringstream hex;
        for (int i = 0; i < SHA256_DIGEST_LENGTH; i++)
            hex << std::uppercase << std::hex << std::setw(2) << std::setfill('0') << (int)hash[i];
        return hex.str();
    }
}

bool verifyCheckMacValue(
    const std::map<std::string, std::string> &params,
    const std::string &hashKey, const std::string &hashIv,
    const std::string &method = "sha256"
) {
    auto it = params.find("CheckMacValue");
    if (it == params.end()) return false;
    std::string received = it->second;
    std::string calculated = generateCheckMacValue(params, hashKey, hashIv, method);
    return (received.size() == calculated.size()) &&
           (CRYPTO_memcmp(received.data(), calculated.data(), received.size()) == 0);
}
```

---

### Rust

> ⚠️ **Rust 特有陷阱**
> - `urlencoding::encode()` 將空格編碼為 `%20`，需替換為 `+`；同時不編碼 `~`，需手動補 `~→%7e`

> **推薦庫**：`hmac` + `sha2` (RustCrypto 生態) — `Cargo.toml` 加入 `hmac = "0.12"`, `sha2 = "0.10"`

```rust
use sha2::{Sha256, Digest};
use md5;
use std::collections::BTreeMap;
use urlencoding;

fn ecpay_url_encode(source: &str) -> String {
    // urlencoding::encode 空格→%20，需替換為 +
    let encoded = urlencoding::encode(source).replace("%20", "+").replace("~", "%7e");
    let mut lower = encoded.to_lowercase();
    let replacements = [
        ("%2d", "-"), ("%5f", "_"), ("%2e", "."), ("%21", "!"),
        ("%2a", "*"), ("%28", "("), ("%29", ")"),
    ];
    for (from, to) in &replacements {
        lower = lower.replace(from, to);
    }
    lower
}

fn generate_check_mac_value(
    params: &BTreeMap<String, String>,
    hash_key: &str, hash_iv: &str, method: &str,
) -> String {
    // 1. 過濾 + 排序（BTreeMap 預設排序，但需不區分大小寫）
    let mut sorted: Vec<(String, String)> = params.iter()
        .filter(|(k, _)| k.as_str() != "CheckMacValue")
        .map(|(k, v)| (k.clone(), v.clone()))
        .collect();
    sorted.sort_by(|a, b| a.0.to_lowercase().cmp(&b.0.to_lowercase()));
    // 3. 組合
    let param_str: String = sorted.iter()
        .map(|(k, v)| format!("{}={}", k, v))
        .collect::<Vec<_>>()
        .join("&");
    let raw = format!("HashKey={}&{}&HashIV={}", hash_key, param_str, hash_iv);
    // 4. ECPay URL encode
    let encoded = ecpay_url_encode(&raw);
    // 5. Hash + 6. 轉大寫
    if method == "md5" {
        format!("{:X}", md5::compute(encoded.as_bytes()))
    } else {
        let mut hasher = Sha256::new();
        hasher.update(encoded.as_bytes());
        format!("{:X}", hasher.finalize())
    }
}

fn verify_check_mac_value(
    params: &BTreeMap<String, String>,
    hash_key: &str, hash_iv: &str, method: &str,
) -> bool {
    let received = match params.get("CheckMacValue") {
        Some(v) => v.as_bytes(),
        None => return false,
    };
    let calculated = generate_check_mac_value(params, hash_key, hash_iv, method);
    let calc_bytes = calculated.as_bytes();
    use subtle::ConstantTimeEq;
    received.ct_eq(calc_bytes).into()
}
```

需要在 `Cargo.toml` 中加入：
```toml
[dependencies]
sha2 = "0.10"
md5 = "0.10"
urlencoding = "2.1"
subtle = "2.5"
```

---

### Swift

> ⚠️ **Swift 特有陷阱**
> - `addingPercentEncoding()` 將空格編碼為 `%20`，需替換為 `+`
> - 需使用明確白名單（`CharacterSet`）確保 `~` 等字元被正確編碼

> **推薦庫**：iOS 13+ 使用 `CryptoKit`；若需支援更早版本，改用 `CommonCrypto`（已包含在系統框架，無需額外安裝）

```swift
import Foundation
import CryptoKit  // iOS 13+ / macOS 10.15+

// Xcode 專案設定：CryptoKit 是 Apple 原生框架，不需額外安裝

func ecpayUrlEncode(_ source: String) -> String {
    // 使用明確白名單，確保 ~ 等字元正確編碼
    var allowed = CharacterSet.alphanumerics
    allowed.insert(charactersIn: "-_.!*()")
    var encoded = source.addingPercentEncoding(withAllowedCharacters: allowed) ?? source
    // 空格需為 +（addingPercentEncoding 會將空格編碼為 %20）
    encoded = encoded.replacingOccurrences(of: "%20", with: "+")
    // ~ 不在白名單中，addingPercentEncoding 會編碼為 %7E，轉小寫後為 %7e ✓
    encoded = encoded.lowercased()
    let replacements: [(String, String)] = [
        ("%2d", "-"), ("%5f", "_"), ("%2e", "."), ("%21", "!"),
        ("%2a", "*"), ("%28", "("), ("%29", ")"),
    ]
    for (from, to) in replacements {
        encoded = encoded.replacingOccurrences(of: from, with: to)
    }
    return encoded
}

func generateCheckMacValue(
    params: [String: String], hashKey: String, hashIv: String,
    method: String = "sha256"
) -> String {
    // 1. 移除 CheckMacValue + 不區分大小寫排序
    let filtered = params.filter { $0.key != "CheckMacValue" }
    let sorted = filtered.sorted { $0.key.lowercased() < $1.key.lowercased() }
    // 3. 組合字串
    let paramStr = sorted.map { "\($0.key)=\($0.value)" }.joined(separator: "&")
    let raw = "HashKey=\(hashKey)&\(paramStr)&HashIV=\(hashIv)"
    // 4. ECPay URL encode
    let encoded = ecpayUrlEncode(raw)
    // 5. Hash（使用 CryptoKit，iOS 13+ / macOS 10.15+）
    let data = Data(encoded.utf8)
    if method == "md5" {
        let digest = Insecure.MD5.hash(data: data)
        return digest.map { String(format: "%02X", $0) }.joined()
    } else {
        let digest = SHA256.hash(data: data)
        return digest.map { String(format: "%02X", $0) }.joined()
    }
}

func verifyCheckMacValue(
    params: [String: String], hashKey: String, hashIv: String,
    method: String = "sha256"
) -> Bool {
    guard let received = params["CheckMacValue"] else { return false }
    let calculated = generateCheckMacValue(params: params, hashKey: hashKey, hashIv: hashIv, method: method)
    // timing-safe：用 HMAC 間接比較（isValidAuthenticationCode 為 constant-time）
    guard received.count == calculated.count else { return false }
    let key = SymmetricKey(data: Data(hashKey.utf8))
    return HMAC<SHA256>.isValidAuthenticationCode(
        HMAC<SHA256>.authenticationCode(for: Data(received.utf8), using: key),
        authenticating: Data(calculated.utf8), using: key
    )
}

// === Fallback: 手動 XOR（iOS 12 以下，⚠️ 有 -O 編譯最佳化風險）===
// let rBytes = Array(received.utf8)
// let cBytes = Array(calculated.utf8)
// let padded = rBytes.count == cBytes.count ? cBytes : rBytes
// var result: UInt8 = rBytes.count == cBytes.count ? 0 : 1
// for i in 0..<rBytes.count { result |= rBytes[i] ^ padded[i] }
// return result == 0
```

> **推薦**：使用 CryptoKit HMAC 間接比較（macOS 10.15+ / iOS 13+），避免手動 XOR 被 `-O` 編譯最佳化消除的風險。
> 原理：對雙方字串分別計算 HMAC，比較 HMAC 值。即使攻擊者知道 HMAC 結果，仍無法推導出原始 CheckMacValue。

---

### Kotlin

> ⚠️ **Kotlin 特有陷阱**
> - `URLEncoder.encode` 行為與 Java 相同（空格→`+`），但部分 JVM 不編碼 `~`，需手動補 `~→%7e`

```kotlin
import java.net.URLEncoder
import java.nio.charset.StandardCharsets
import java.security.MessageDigest

// Gradle: 不需額外依賴，使用 JDK 標準庫

fun ecpayUrlEncode(source: String): String {
    var encoded = URLEncoder.encode(source, StandardCharsets.UTF_8) // 空格→+
    encoded = encoded.lowercase()
    return encoded
        .replace("%2d", "-").replace("%5f", "_").replace("%2e", ".")
        .replace("%21", "!").replace("%2a", "*")
        .replace("%28", "(").replace("%29", ")")
        .replace("~", "%7e")  // PHP urlencode('~') → %7E
}

fun generateCheckMacValue(
    params: Map<String, String>,
    hashKey: String,
    hashIv: String,
    method: String = "sha256"
): String {
    // 1. 移除 CheckMacValue + 不區分大小寫排序
    val sorted = params.filterKeys { it != "CheckMacValue" }
        .toSortedMap(String.CASE_INSENSITIVE_ORDER)
    // 3. 組合字串
    val paramStr = sorted.entries.joinToString("&") { "${it.key}=${it.value}" }
    val raw = "HashKey=$hashKey&$paramStr&HashIV=$hashIv"
    // 4. ECPay URL encode
    val encoded = ecpayUrlEncode(raw)
    // 5. Hash
    val algorithm = if (method == "md5") "MD5" else "SHA-256"
    val digest = MessageDigest.getInstance(algorithm)
        .digest(encoded.toByteArray(StandardCharsets.UTF_8))
    // 6. 轉大寫
    return digest.joinToString("") { "%02X".format(it) }
}

fun verifyCheckMacValue(
    params: Map<String, String>,
    hashKey: String,
    hashIv: String,
    method: String = "sha256"
): Boolean {
    val received = params["CheckMacValue"] ?: return false
    val calculated = generateCheckMacValue(params, hashKey, hashIv, method)
    return MessageDigest.isEqual(
        received.toByteArray(StandardCharsets.UTF_8),
        calculated.toByteArray(StandardCharsets.UTF_8)
    )
}
```

---

### Ruby

> **⚠️ CGI.escape 與特殊字元**：現代 Ruby 的 `CGI.escape` 安全字元集為 `a-zA-Z0-9_.-~`，**不編碼 `~`**（空格→`+`）。注意：舊版 Ruby（極少數環境）安全字元集為 `a-zA-Z0-9_.-`（不含 `~`），會將 `~` 輸出為 `%7E`。無論如何，下方 `.gsub('~', '%7e')` 確保最終輸出符合 PHP `urlencode` 的 `%7E`（再經 strtolower 轉為 `%7e`）。
> `!*'()` 已由 `CGI.escape` 正常編碼（`%21/%2A/%27/%28/%29`），.NET 替換後 `!*()` 還原為原字元；`'→%27` 不在 .NET 替換表中（保持 `%27`）。`.gsub("'", '%27')` 為冪等安全措施。

```ruby
require 'digest'
require 'cgi'
require 'openssl'

# Gemfile: 不需額外依賴，使用 Ruby 標準庫

def ecpay_url_encode(source)
  encoded = CGI.escape(source) # 空格→+
  encoded = encoded.gsub("'", '%27') # 冪等安全措施：CGI.escape 已將 ' 編碼為 %27；%27 不在 .NET 替換表中，不會被還原
  encoded = encoded.downcase
  {
    '%2d' => '-', '%5f' => '_', '%2e' => '.', '%21' => '!',
    '%2a' => '*', '%28' => '(', '%29' => ')',
  }.each { |from, to| encoded = encoded.gsub(from, to) }
  encoded = encoded.gsub('~', '%7e') # PHP urlencode('~') → %7E
  encoded
end

def generate_check_mac_value(params, hash_key, hash_iv, method = 'sha256')
  # 1. 移除 CheckMacValue + 不區分大小寫排序
  filtered = params.reject { |k, _| k == 'CheckMacValue' }
  sorted = filtered.sort_by { |k, _| k.downcase }
  # 3. 組合字串
  param_str = sorted.map { |k, v| "#{k}=#{v}" }.join('&')
  raw = "HashKey=#{hash_key}&#{param_str}&HashIV=#{hash_iv}"
  # 4. ECPay URL encode
  encoded = ecpay_url_encode(raw)
  # 5. Hash
  hashed = if method == 'md5'
    Digest::MD5.hexdigest(encoded)
  else
    Digest::SHA256.hexdigest(encoded)
  end
  # 6. 轉大寫
  hashed.upcase
end

def verify_check_mac_value(params, hash_key, hash_iv, method = 'sha256')
  received = params['CheckMacValue']
  return false unless received
  calculated = generate_check_mac_value(params, hash_key, hash_iv, method)
  OpenSSL.secure_compare(received, calculated) # Ruby 2.5+, timing-safe
end
```

## 測試向量

使用以下資料驗證你的實作：

### SHA256 測試向量（金流 AIO）

```
參數：
  MerchantID=3002607
  MerchantTradeNo=Test1234567890
  MerchantTradeDate=2025/01/01 12:00:00
  PaymentType=aio
  TotalAmount=100
  TradeDesc=測試
  ItemName=測試商品
  ReturnURL=https://example.com/notify
  ChoosePayment=ALL
  EncryptType=1

HashKey=pwFHCqoQZGmho4w6
HashIV=EkRm7iFT261dpevs
Method=SHA256
```

產生步驟：
1. 排序後組合：`HashKey=pwFHCqoQZGmho4w6&ChoosePayment=ALL&EncryptType=1&ItemName=測試商品&MerchantID=3002607&MerchantTradeDate=2025/01/01 12:00:00&MerchantTradeNo=Test1234567890&PaymentType=aio&ReturnURL=https://example.com/notify&TotalAmount=100&TradeDesc=測試&HashIV=EkRm7iFT261dpevs`
2. ecpayUrlEncode → 轉小寫 + .NET 替換：`hashkey%3dpwfhcqoqzgmho4w6%26choosepayment%3dall%26encrypttype%3d1%26itemname%3d%e6%b8%ac%e8%a9%a6%e5%95%86%e5%93%81%26merchantid%3d3002607%26merchanttradedate%3d2025%2f01%2f01+12%3a00%3a00%26merchanttradeno%3dtest1234567890%26paymenttype%3daio%26returnurl%3dhttps%3a%2f%2fexample.com%2fnotify%26totalamount%3d100%26tradedesc%3d%e6%b8%ac%e8%a9%a6%26hashiv%3dekrm7ift261dpevs`
3. SHA256 → 大寫

預期結果（SHA256）：`291CBA324D31FB5A4BBBFDF2CFE5D32598524753AFD4959C3BF590C5B2F57FB2`

> **驗證方式**：用你的實作跑一遍，結果必須等於上方預期值。
> 因為 SHA256 是確定性演算法，相同輸入必然產生相同輸出。
> 建議先用 Python 實作確認結果，再與目標語言比對。

### MD5 測試向量（國內物流）

```
參數：
  MerchantID=2000132
  LogisticsType=CVS
  LogisticsSubType=UNIMART
  MerchantTradeDate=2025/01/01 12:00:00

HashKey=5294y06JbISpM5x9
HashIV=v77hoKGq4kWxNNIS
Method=MD5
```

產生步驟：
1. 排序後組合：`HashKey=5294y06JbISpM5x9&LogisticsSubType=UNIMART&LogisticsType=CVS&MerchantID=2000132&MerchantTradeDate=2025/01/01 12:00:00&HashIV=v77hoKGq4kWxNNIS`
2. ecpayUrlEncode → 轉小寫 + .NET 替換：`hashkey%3d5294y06jbispm5x9%26logisticssubtype%3dunimart%26logisticstype%3dcvs%26merchantid%3d2000132%26merchanttradedate%3d2025%2f01%2f01+12%3a00%3a00%26hashiv%3dv77hokgq4kwxnnis`
3. MD5 → 大寫

預期結果（MD5）：`545E6146FD45BDA683C88454DB34CE8D`

各語言實作驗證範例（Python）：
```python
# SHA256 測試向量驗證
params_sha256 = {
    'MerchantID': '3002607',
    'MerchantTradeNo': 'Test1234567890',
    'MerchantTradeDate': '2025/01/01 12:00:00',
    'PaymentType': 'aio',
    'TotalAmount': '100',
    'TradeDesc': '測試',
    'ItemName': '測試商品',
    'ReturnURL': 'https://example.com/notify',
    'ChoosePayment': 'ALL',
    'EncryptType': '1',
}
result = generate_check_mac_value(params_sha256, 'pwFHCqoQZGmho4w6', 'EkRm7iFT261dpevs', 'sha256')
print(f'SHA256 CheckMacValue: {result}')
assert result == '291CBA324D31FB5A4BBBFDF2CFE5D32598524753AFD4959C3BF590C5B2F57FB2'

# MD5 測試向量驗證
params_md5 = {
    'MerchantID': '2000132',
    'LogisticsType': 'CVS',
    'LogisticsSubType': 'UNIMART',
    'MerchantTradeDate': '2025/01/01 12:00:00',
}
result = generate_check_mac_value(params_md5, '5294y06JbISpM5x9', 'v77hoKGq4kWxNNIS', 'md5')
print(f'MD5 CheckMacValue: {result}')
assert result == '545E6146FD45BDA683C88454DB34CE8D'
```

### 特殊字元 `'` 測試向量（驗證 Node.js/TypeScript 修正）

```
參數：
  MerchantID=3002607
  ItemName=Tom's Shop
  TotalAmount=100

HashKey=pwFHCqoQZGmho4w6
HashIV=EkRm7iFT261dpevs
Method=SHA256
```

> `encodeURIComponent("'")` 不編碼 `'`，但 PHP `urlencode("'")` = `%27`。
> 若未加 `.replace(/'/g, '%27')`，CMV 計算結果將與 ECPay 不一致。

產生步驟：
1. 排序後組合：`HashKey=pwFHCqoQZGmho4w6&ItemName=Tom's Shop&MerchantID=3002607&TotalAmount=100&HashIV=EkRm7iFT261dpevs`
2. ecpayUrlEncode：`hashkey%3dpwfhcqoqzgmho4w6%26itemname%3dtom%27s+shop%26merchantid%3d3002607%26totalamount%3d100%26hashiv%3dekrm7ift261dpevs`
3. SHA256 → 大寫

預期結果（SHA256）：`CF0A3D4901D99459D8641516EC57210700E8A5C9AB26B1D021301E9CB93EF78D`

> 用此測試向量驗證你的 Node.js/TypeScript 實作。如果結果不符，檢查 `ecpayUrlEncode` 是否有 `.replace(/'/g, '%27')`。

## 常見錯誤

1. **排序不正確** — 必須不區分大小寫排序（case-insensitive）
2. **URL encode 行為不同** — Node.js 的 `encodeURIComponent` 空格是 `%20` 不是 `+`
3. **沒有轉小寫** — URL encode 後必須全部轉小寫
4. **遺漏 .NET 替換** — 7 個特殊字元必須還原
5. **Hash 沒轉大寫** — 最後結果必須全部大寫
6. **字串編碼** — 必須使用 UTF-8
7. **國內物流用了 SHA256** — 國內物流是 MD5，不是 SHA256

## 相關文件

- PHP SDK 原始碼:`scripts/SDK_PHP/src/Services/CheckMacValueService.php`(`generate()` @ line 76、`generateHash()` @ line 98)
- URL Encode 原始碼:`scripts/SDK_PHP/src/Services/UrlService.php`(`ecpayUrlEncode()` @ line 13-48)
- AES 加解密：[guides/14-aes-encryption.md](./14-aes-encryption.md)
- 機器可讀測試向量（CI/自動化測試用）：`test-vectors/checkmacvalue.json`

## 官方規格參照

- AIO 金流 CheckMacValue：`references/Payment/全方位金流API技術文件.md` → §附錄 / 檢查碼機制說明
- 國內物流 CheckMacValue：`references/Logistics/物流整合API技術文件.md` → §附錄 / 檢查碼機制說明
- 電子票證 CheckMacValue（公式與 AIO 不同）：`references/Ecticket/電子票證API技術文件.md` → §附錄 / 檢查碼機制說明
