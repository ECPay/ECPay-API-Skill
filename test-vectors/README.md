# ECPay 測試向量

本目錄提供 CheckMacValue、AES 加密/解密、URL Encode 差異比對的測試向量，可用於自動化測試和 CI 驗證。

完整說明與各語言實作見：
- CheckMacValue: [guides/13-checkmacvalue.md](../guides/13-checkmacvalue.md) §測試向量
- AES 加密: [guides/14-aes-encryption.md](../guides/14-aes-encryption.md) §測試向量

## 驗證指令碼

```bash
pip install pycryptodome
python test-vectors/verify.py
```

## 向量清單

### CheckMacValue (8 vectors)

| # | 名稱 | 重點驗證 |
|---|------|---------|
| 1 | SHA256 基本測試 | 標準 AIO 金流流程 |
| 2 | MD5 測試 | 國內物流 |
| 3 | 特殊字元 `'` | Node.js/TypeScript 的 encodeURIComponent 不編碼 `'` |
| 4 | 特殊字元 `~` | 各語言 `~` → `%7E` 替換 |
| 5 | **空格處理** | `%20` vs `+` 陷阱（Node.js、Rust 預設產生 `%20`） |
| 6 | **Callback 驗證** | 模擬收到付款通知，驗證 CMV 比對流程 |
| 7 | **E-Ticket CMV** | 電子票證使用完全不同的公式：`SHA256(toLowerCase(URL_encode(Key+JSON+IV)))` |
| 8 | **AllowanceByCollegiate MD5** | B2C 發票唯一帶 CMV 的 API，使用 MD5 + 發票帳號（非 AIO 帳號）|

### AES 加密/解密 (6 vectors)

| # | 名稱 | 重點驗證 |
|---|------|---------|
| 1 | 基本測試（插入順序 JSON key） | Python/Node.js/C#/Ruby 等插入順序語言 |
| 2 | 基本測試（字母序 JSON key） | Go/Java/Swift 等字母序語言 |
| 3 | 特殊字元 `!*'()~` | 各語言 URL encode 差異 |
| 4 | **PKCS7 16-byte 邊界** | plaintext 剛好 32 bytes 時的 padding 行為 |
| 5 | **UTF-8 中文字元** | `json.dumps(ensure_ascii=False)` / `SetEscapeHTML(false)` 驗證 |
| 6 | **AES 解密（反向驗證）** | Base64 → decrypt → URL decode → JSON（callback 解密流程） |

### URL Encode 函式比對 (4 vectors)

| # | 輸入 | 重點驗證 |
|---|------|---------|
| 1 | `Items (Special)~Test` | `()` .NET 替換 + 大小寫差異 |
| 2 | `Tom's Shop!` | `!` .NET 替換 + 大小寫差異 |
| 3 | `price=100&item=test*2` | `*` .NET 替換（hex 含字母 `%2A`→`*`）|
| 4 | `file_name-v2.0` | 結果相同的情境（不可因此混用兩函式）|

> **⚠ 重要**：`ecpayUrlEncode`（CMV 用）和 `aesUrlEncode`（AES 用）是兩個不同函式，不可混用。
> 詳見 [url-encode-comparison.json](url-encode-comparison.json)。
