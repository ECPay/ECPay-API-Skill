# Swift — ECPay 整合程式規範

> 本檔為 AI 生成 ECPay 整合程式碼時的 Swift 專屬規範。
> 加密函式：[guides/13 §Swift](../13-checkmacvalue.md) + [guides/14 §Swift](../14-aes-encryption.md)
> E2E 範例：[guides/24 §Swift](../24-multi-language-integration.md)

## 版本與環境

- **最低版本**：Swift 5.7+（`if let` shorthand、regex builder）
- **推薦版本**：Swift 5.9+
- **加密**：`CommonCrypto`（系統框架）或 `CryptoSwift`（第三方）
- **平台**：iOS 15+ / macOS 12+ / Server-side (Vapor)

## 推薦依賴

```swift
// Package.swift 或 SPM
dependencies: [
    .package(url: "https://github.com/krzyzanowskim/CryptoSwift", from: "1.8.0"),
    // Server-side:
    .package(url: "https://github.com/vapor/vapor", from: "4.90.0"),
]
```

> **CommonCrypto vs CryptoSwift**：iOS 可用 `CommonCrypto`（系統內建），Server-side 建議用 `CryptoSwift`。guides/13、14 的範例使用 `CryptoSwift`。

## 命名慣例

```swift
// 函式 / 變數 / 參數：camelCase（Swift API Design Guidelines）
func generateCheckMacValue(params: [String: String], hashKey: String, hashIV: String) -> String
let merchantTradeNo = "ORDER\(Int(Date().timeIntervalSince1970))"

// 型別 / 協議 / 列舉：PascalCase
struct EcpayPaymentClient { }
protocol EcpayServiceProtocol { }
enum PaymentMethod: String { case credit = "Credit" }

// 常數 / 靜態屬性：camelCase（Swift 慣例，非 UPPER_SNAKE）
static let paymentURL = "https://payment.ecpay.com.tw/Cashier/AioCheckOut/V5"

// 檔案：PascalCase.swift
// EcpayPayment.swift, EcpayAES.swift, EcpayCallback.swift
```

## 型別定義

```swift
struct AioParams: Codable {
    let merchantID: String
    let merchantTradeNo: String
    let merchantTradeDate: String   // yyyy/MM/dd HH:mm:ss
    let paymentType: String         // "aio"
    let totalAmount: String         // ⚠️ 整數字串
    let tradeDesc: String
    let itemName: String
    let returnURL: String
    let choosePayment: String
    let encryptType: String         // "1"
    var checkMacValue: String?

    enum CodingKeys: String, CodingKey {
        case merchantID = "MerchantID"
        case merchantTradeNo = "MerchantTradeNo"
        case merchantTradeDate = "MerchantTradeDate"
        case paymentType = "PaymentType"
        case totalAmount = "TotalAmount"
        case tradeDesc = "TradeDesc"
        case itemName = "ItemName"
        case returnURL = "ReturnURL"
        case choosePayment = "ChoosePayment"
        case encryptType = "EncryptType"
        case checkMacValue = "CheckMacValue"
    }
}

struct AesRequest: Encodable {
    let merchantID: String
    let rqHeader: RqHeader
    let data: String

    enum CodingKeys: String, CodingKey {
        case merchantID = "MerchantID"
        case rqHeader = "RqHeader"
        case data = "Data"
    }
}

struct RqHeader: Encodable {
    let timestamp: Int
    /// 依服務設定：B2C 發票 = "3.0.0"，B2B/票證 = "1.0.0"，站內付 2.0 = nil（省略）
    let revision: String?

    enum CodingKeys: String, CodingKey {
        case timestamp = "Timestamp"
        case revision = "Revision"
    }

    func encode(to encoder: Encoder) throws {
        var container = encoder.container(keyedBy: CodingKeys.self)
        try container.encode(timestamp, forKey: .timestamp)
        if let rev = revision {
            try container.encode(rev, forKey: .revision)
        }
    }
}

struct AesResponse: Decodable {
    let transCode: Int
    let transMsg: String
    let data: String

    enum CodingKeys: String, CodingKey {
        case transCode = "TransCode"
        case transMsg = "TransMsg"
        case data = "Data"
    }
}

// ⚠️ RtnCode 型別依協議：CMV 服務（AIO/物流）→ String；AES-JSON 服務（ECPG/發票）→ Int（整數）
// 以下為 CMV 服務（AIO callback）範例
struct CallbackParams: Decodable {
    let rtnCode: String         // AIO/物流：字串 "1"；ECPG/發票解密後：整數（應改 Int）
    let merchantTradeNo: String
    let checkMacValue: String

    enum CodingKeys: String, CodingKey {
        case rtnCode = "RtnCode"
        case merchantTradeNo = "MerchantTradeNo"
        case checkMacValue = "CheckMacValue"
    }
}
```

## 錯誤處理

```swift
enum EcpayError: Error, LocalizedError {
    case httpError(statusCode: Int)
    case rateLimited
    case transportError(transCode: Int, message: String)
    case businessError(rtnCode: String, message: String)
    case aesError(String)
    case cmvMismatch

    var errorDescription: String? {
        switch self {
        case .httpError(let code): return "HTTP \(code)"
        case .rateLimited: return "Rate Limited (403) — 需等待約 30 分鐘"
        case .transportError(let tc, let msg): return "TransCode=\(tc): \(msg)"
        case .businessError(let rc, let msg): return "RtnCode=\(rc): \(msg)"
        case .aesError(let msg): return "AES: \(msg)"
        case .cmvMismatch: return "CheckMacValue verification failed"
        }
    }
}

func callAesAPI(url: String, request: AesRequest, hashKey: String, hashIV: String) async throws -> [String: Any] {
    var urlRequest = URLRequest(url: URL(string: url)!)
    urlRequest.httpMethod = "POST"
    urlRequest.setValue("application/json", forHTTPHeaderField: "Content-Type")
    urlRequest.httpBody = try JSONEncoder().encode(request)
    urlRequest.timeoutInterval = 30

    let (data, response) = try await URLSession.shared.data(for: urlRequest)
    guard let httpResp = response as? HTTPURLResponse else {
        throw EcpayError.httpError(statusCode: 0)
    }

    if httpResp.statusCode == 403 { throw EcpayError.rateLimited }
    guard (200..<300).contains(httpResp.statusCode) else {
        throw EcpayError.httpError(statusCode: httpResp.statusCode)
    }

    let result = try JSONDecoder().decode(AesResponse.self, from: data)

    // 雙層錯誤檢查
    guard result.transCode == 1 else {
        throw EcpayError.transportError(transCode: result.transCode, message: result.transMsg)
    }
    let decrypted = try ecpayAesDecrypt(result.data, hashKey: hashKey, hashIV: hashIV)
    // ⚠️ AES-JSON 服務（ECPG/發票/物流 v2）解密後 RtnCode 為整數 1（非字串 "1"）
    // JSONSerialization 將 JSON 整數對應為 NSNumber/Int，不可用 as? String（轉型永遠失敗）
    guard (decrypted["RtnCode"] as? Int) == 1 else {
        throw EcpayError.businessError(
            rtnCode: "\(decrypted["RtnCode"] ?? "")",
            message: decrypted["RtnMsg"] as? String ?? "")
    }
    return decrypted
}
```

## Callback Handler 模板（Vapor）

```swift
import Vapor
import CryptoKit  // for timing-safe HMAC comparison

func routes(_ app: Application) throws {
    app.post("ecpay", "callback") { req async throws -> Response in
        let params = try req.content.decode([String: String].self)
        var mutableParams = params

        // 1. Timing-safe CMV 驗證
        guard let receivedCmv = mutableParams.removeValue(forKey: "CheckMacValue") else {
            throw Abort(.badRequest, reason: "Missing CheckMacValue")
        }
        let expectedCmv = generateCheckMacValue(params: mutableParams, hashKey: hashKey, hashIV: hashIV)

        // timing-safe 字串比較（CryptoKit HMAC 間接比較，macOS 10.15+ / iOS 13+）
        let key = SymmetricKey(data: Data(hashKey.utf8))
        guard HMAC<SHA256>.isValidAuthenticationCode(
            HMAC<SHA256>.authenticationCode(for: Data(receivedCmv.utf8), using: key),
            authenticating: Data(expectedCmv.utf8), using: key
        ) else {
            throw Abort(.badRequest, reason: "CheckMacValue Error")
        }

        // 2. RtnCode 是字串
        if params["RtnCode"] == "1" {
            // 處理成功
        }

        // 3. HTTP 200 + "1|OK"
        return Response(status: .ok, body: .init(string: "1|OK"))
    }
}
```

> ⚠️ ECPay Callback URL 僅支援 port 80 (HTTP) / 443 (HTTPS)，開發環境使用 ngrok 轉發到本機任意 port。

## Timing-Safe 比較函式

```swift
import CryptoKit

/// Timing-safe CheckMacValue 驗證 — 使用 CryptoKit HMAC 間接比較
/// HMAC<SHA256>.isValidAuthenticationCode 為 constant-time，
/// 避免手動 XOR 被 Swift -O 編譯最佳化消除的風險（macOS 10.15+ / iOS 13+）
func verifyCheckMacValueTimingSafe(_ received: String, _ expected: String, hashKey: String) -> Bool {
    guard received.count == expected.count else { return false }
    let key = SymmetricKey(data: Data(hashKey.utf8))
    return HMAC<SHA256>.isValidAuthenticationCode(
        HMAC<SHA256>.authenticationCode(for: Data(received.utf8), using: key),
        authenticating: Data(expected.utf8), using: key
    )
}
```

> ⚠️ 請勿使用 `==` 比較 CheckMacValue — 標準字串比較會因第一個不同字元而提前返回，造成 timing leak。
> 請勿使用手動 XOR 實作 — Swift `-O` 最佳化可能消除常數時間保證。請用 CryptoKit 的 `HMAC<SHA256>.isValidAuthenticationCode`。

## 日誌與監控

```swift
import os

// 推薦 os.Logger（Apple 平台）或 swift-log（Server-side）
let logger = Logger(subsystem: "com.example.ecpay", category: "payment")

// ⚠️ 絕不記錄 HashKey / HashIV / CheckMacValue
// ✅ 記錄：API 呼叫結果、交易編號、錯誤訊息
logger.info("ECPay API 呼叫成功: MerchantTradeNo=\(merchantTradeNo)")
logger.error("ECPay API 錯誤: TransCode=\(transCode), RtnCode=\(rtnCode)")

// Server-side (Vapor) 使用 swift-log：
// import Logging
// let logger = Logger(label: "ecpay")
```

> **日誌安全規則**：HashKey、HashIV、CheckMacValue 為機敏資料，嚴禁出現在任何日誌、錯誤回報或前端回應中。

## 日期與時區

```swift
import Foundation

// ⚠️ ECPay 所有時間欄位皆為台灣時間（UTC+8）
let twTimeZone = TimeZone(identifier: "Asia/Taipei")!

// MerchantTradeDate 格式：yyyy/MM/dd HH:mm:ss（非 ISO 8601）
func merchantTradeDate() -> String {
    let formatter = DateFormatter()
    formatter.dateFormat = "yyyy/MM/dd HH:mm:ss"
    formatter.timeZone = twTimeZone
    return formatter.string(from: Date())
    // → "2026/03/11 12:10:41"
}

// AES RqHeader.Timestamp：Unix 秒數
let timestamp = Int(Date().timeIntervalSince1970) // Double → Int 截斷
```

## 環境變數

```swift
import Foundation

struct EcpayConfig {
    let merchantID: String
    let hashKey: String
    let hashIV: String
    let baseURL: String

    static func load() -> EcpayConfig {
        let env = ProcessInfo.processInfo.environment
        let ecpayEnv = env["ECPAY_ENV"] ?? "stage"
        return EcpayConfig(
            merchantID: env["ECPAY_MERCHANT_ID"] ?? "",
            hashKey: env["ECPAY_HASH_KEY"] ?? "",
            hashIV: env["ECPAY_HASH_IV"] ?? "",
            baseURL: ecpayEnv == "stage"
                ? "https://payment-stage.ecpay.com.tw"
                : "https://payment.ecpay.com.tw"
        )
    }
}
```

## URL Encode 注意

```swift
// ⚠️ Swift 的 addingPercentEncoding() 有兩個陷阱：
//   1. 空格編碼為 %20 而非 + → ECPay 要求 +（%20 → +）
//   2. 標準 .urlQueryAllowed 不編碼 ~ → ECPay 要求 %7e（~ → %7e，小寫 e）
//      guides/13 實作使用自訂 CharacterSet 排除 ~，使其自動編碼為 %7E，再經 lowercased() 成為 %7e
// guides/13 的 ecpayUrlEncode 已處理以上轉換
// 請直接使用 guides/13 提供的函式，勿自行實作
```

## CommonCrypto 替代方案

```swift
import CommonCrypto

// ⚠️ CommonCrypto 為系統內建框架，無需第三方依賴
// 適用於 iOS/macOS 專案不想引入 CryptoSwift 的情況
// SHA256 範例：
func sha256(_ string: String) -> String {
    let data = Data(string.utf8)
    var hash = [UInt8](repeating: 0, count: Int(CC_SHA256_DIGEST_LENGTH))
    data.withUnsafeBytes { _ = CC_SHA256($0.baseAddress, CC_LONG(data.count), &hash) }
    return hash.map { String(format: "%02X", $0) }.joined()
}

// AES-128-CBC 範例：
func aesCBCEncrypt(data: Data, key: Data, iv: Data) -> Data? {
    var outLength = 0
    var outBytes = [UInt8](repeating: 0, count: data.count + kCCBlockSizeAES128)
    let status = key.withUnsafeBytes { keyBytes in
        iv.withUnsafeBytes { ivBytes in
            data.withUnsafeBytes { dataBytes in
                CCCrypt(CCOperation(kCCEncrypt), CCAlgorithm(kCCAlgorithmAES),
                        CCOptions(kCCOptionPKCS7Padding),
                        keyBytes.baseAddress, kCCKeySizeAES128,
                        ivBytes.baseAddress,
                        dataBytes.baseAddress, data.count,
                        &outBytes, outBytes.count, &outLength)
            }
        }
    }
    guard status == kCCSuccess else { return nil }
    return Data(outBytes.prefix(outLength))
}
// 完整實作詳見 guides/14 §Swift
```

## 單元測試模式

```swift
import XCTest

final class EcpayTests: XCTestCase {
    func testCmvSha256() {
        let params: [String: String] = [
            "MerchantID": "3002607",
            // ... test vector params ...
        ]
        let result = generateCheckMacValue(params: params, hashKey: "pwFHCqoQZGmho4w6", hashIV: "EkRm7iFT261dpevs")
        XCTAssertEqual(result, "291CBA324D31FB5A4BBBFDF2CFE5D32598524753AFD4959C3BF590C5B2F57FB2")
    }

    func testAesRoundtrip() throws {
        let data: [String: Any] = ["MerchantID": "2000132", "BarCode": "/1234567"]
        let encrypted = try ecpayAesEncrypt(data, hashKey: "ejCk326UnaZWKisg", hashIV: "q9jcZX8Ib9LM8wYk")
        let decrypted = try ecpayAesDecrypt(encrypted, hashKey: "ejCk326UnaZWKisg", hashIV: "q9jcZX8Ib9LM8wYk")
        XCTAssertEqual(decrypted["MerchantID"] as? String, "2000132")
    }
}
```

## Linter / Formatter

```bash
# SwiftLint（推薦）
# 安裝：brew install swiftlint
# 設定：.swiftlint.yml
# disabled_rules:
#   - line_length
# opt_in_rules:
#   - force_unwrapping
swiftlint
swift-format format --in-place .
```
