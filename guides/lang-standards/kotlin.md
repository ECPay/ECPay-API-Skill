# Kotlin — ECPay 整合程式規範

> 本檔為 AI 生成 ECPay 整合程式碼時的 Kotlin 專屬規範。
> 加密函式：[guides/13 §Kotlin](../13-checkmacvalue.md) + [guides/14 §Kotlin](../14-aes-encryption.md)
> E2E 範例：[guides/24 §Kotlin](../24-multi-language-integration.md)

## 版本與環境

- **最低版本**：Kotlin 1.8+、JDK 11+
- **推薦版本**：Kotlin 1.9+、JDK 17+（支援 coroutines 搭配 Ktor 非同步 HTTP）
- **建置工具**：Gradle（Kotlin DSL）或 Maven
- **加密**：`javax.crypto` 標準庫（與 Java 共用）

## 推薦依賴

```kotlin
// build.gradle.kts
dependencies {
    implementation("com.google.code.gson:gson:2.10.1")
    // 若用 Ktor：
    // implementation("io.ktor:ktor-client-cio:2.3.0")
    // implementation("io.ktor:ktor-client-content-negotiation:2.3.0")
}
```

## 命名慣例

```kotlin
// 函式 / 變數：camelCase
fun generateCheckMacValue(params: Map<String, String>, hashKey: String, hashIv: String): String

// 類別：PascalCase
class EcpayPaymentClient(private val config: EcpayConfig)

// 常數：UPPER_SNAKE_CASE（companion object 內）
companion object {
    const val ECPAY_PAYMENT_URL = "https://payment.ecpay.com.tw/Cashier/AioCheckOut/V5"
}

// 套件名：全小寫
package com.example.ecpay

// 檔案名：PascalCase.kt
// EcpayPayment.kt, EcpayAes.kt
```

## 型別定義

```kotlin
data class AioParams(
    val merchantID: String,
    val merchantTradeNo: String,
    val merchantTradeDate: String,  // yyyy/MM/dd HH:mm:ss
    val paymentType: String = "aio",
    val totalAmount: String,        // ⚠️ 整數字串
    val tradeDesc: String,
    val itemName: String,
    val returnURL: String,
    val choosePayment: String = "ALL",
    val encryptType: String = "1",
) {
    /** 轉為 API 送出的 Map（PascalCase key） */
    fun toParamMap(): Map<String, String> = mapOf(
        "MerchantID" to merchantID,
        "MerchantTradeNo" to merchantTradeNo,
        // ...
    )
}

data class AesRequest(
    @SerializedName("MerchantID") val merchantID: String,
    @SerializedName("RqHeader") val rqHeader: RqHeader,
    @SerializedName("Data") val data: String,
)

data class RqHeader(
    @SerializedName("Timestamp") val timestamp: Long,
    // ⚠️ Revision 因服務而異，必須明確傳入：
    // B2C 發票 → "3.0.0" | B2B 發票 / ECTicket → "1.0.0" | 站內付 2.0 → 不傳（omit）
    @SerializedName("Revision") val revision: String? = null,
)

data class AesResponse(
    @SerializedName("TransCode") val transCode: Int,
    @SerializedName("TransMsg") val transMsg: String,
    @SerializedName("Data") val data: String,
)

// ⚠️ RtnCode 為 String
data class CallbackParams(
    val rtnCode: String,         // "1" 非 Int
    val merchantTradeNo: String,
    val checkMacValue: String,
)

data class EcpayConfig(
    val merchantId: String,
    val hashKey: String,
    val hashIv: String,
    val baseUrl: String,
)
```

## 錯誤處理

```kotlin
class EcpayApiException(
    val transCode: Int,
    val rtnCode: String?,
    override val message: String,
) : RuntimeException("TransCode=$transCode, RtnCode=$rtnCode: $message")

// ⚠️ 使用全域共用 HttpClient，勿每次請求建立新實例
private val httpClient = java.net.http.HttpClient.newBuilder()
    .connectTimeout(java.time.Duration.ofSeconds(10))
    .build()

// ⚠️ 必須禁用 HTML escaping — ECPay 不預期 \u003c 格式
private val gson = GsonBuilder().disableHtmlEscaping().create()

fun callAesApi(url: String, request: AesRequest, hashKey: String, hashIv: String): Map<String, Any> {
    val httpReq = java.net.http.HttpRequest.newBuilder()
        .uri(java.net.URI.create(url))
        .header("Content-Type", "application/json")
        .POST(java.net.http.HttpRequest.BodyPublishers.ofString(gson.toJson(request)))
        .timeout(java.time.Duration.ofSeconds(30))
        .build()
    val resp = httpClient.send(httpReq, java.net.http.HttpResponse.BodyHandlers.ofString())

    if (resp.statusCode() == 403) {
        throw EcpayApiException(-1, null, "Rate Limited — 需等待約 30 分鐘")
    }

    val result = gson.fromJson(resp.body(), AesResponse::class.java)

    // 雙層錯誤檢查
    if (result.transCode != 1) {
        throw EcpayApiException(result.transCode, null, result.transMsg)
    }
    // ⚠️ aesDecrypt 回傳原始 JSON String（同 guides/14）；用 JsonObject 讀取欄位
    // 避免 Gson TypeToken<Map<String,Any>> 將整數解析為 Double（toString() 給 "1.0" ≠ "1"）
    val decryptedStr = aesDecrypt(result.data, hashKey, hashIv)
    val jsonData = gson.fromJson(decryptedStr, JsonObject::class.java)
    val rtnCode = jsonData.get("RtnCode")?.asInt ?: -1
    if (rtnCode != 1) {
        throw EcpayApiException(1, "$rtnCode", jsonData.get("RtnMsg")?.asString ?: "")
    }
    return gson.fromJson(decryptedStr, object : com.google.gson.reflect.TypeToken<Map<String, Any>>() {}.type)
}
```

## Callback Handler 模板（Spring Boot）

```kotlin
@RestController
class EcpayCallbackController(
    @Value("\${ecpay.hash-key}") private val hashKey: String,
    @Value("\${ecpay.hash-iv}") private val hashIv: String,
) {
    @PostMapping("/ecpay/callback",
        consumes = [MediaType.APPLICATION_FORM_URLENCODED_VALUE],
        produces = [MediaType.TEXT_PLAIN_VALUE])
    fun handleCallback(@RequestParam params: MutableMap<String, String>): ResponseEntity<String> {
        // 1. Timing-safe CMV 驗證
        val receivedCmv = params.remove("CheckMacValue") ?: return ResponseEntity.badRequest().body("Missing CMV")
        val expectedCmv = generateCheckMacValue(params, hashKey, hashIv)
        if (!MessageDigest.isEqual(receivedCmv.toByteArray(Charsets.UTF_8), expectedCmv.toByteArray(Charsets.UTF_8))) {
            return ResponseEntity.badRequest().body("CheckMacValue Error")
        }

        // 2. RtnCode 是字串
        if (params["RtnCode"] == "1") {
            // 處理成功
        }

        // 3. HTTP 200 + "1|OK"
        return ResponseEntity.ok("1|OK")
    }
}
```

## Callback Handler 模板（Ktor）

```kotlin
fun Application.configureRouting() {
    routing {
        post("/ecpay/callback") {
            val params = call.receiveParameters().toMap().mapValues { it.value.first() }.toMutableMap()
            val receivedCmv = params.remove("CheckMacValue") ?: return@post call.respond(HttpStatusCode.BadRequest)
            val expectedCmv = generateCheckMacValue(params, hashKey, hashIv)
            if (!MessageDigest.isEqual(receivedCmv.toByteArray(Charsets.UTF_8), expectedCmv.toByteArray(Charsets.UTF_8))) {
                return@post call.respondText("CheckMacValue Error", status = HttpStatusCode.BadRequest)
            }
            if (params["RtnCode"] == "1") { /* 處理成功 */ }
            call.respondText("1|OK", ContentType.Text.Plain)
        }
    }
}
```

> ⚠️ ECPay Callback URL 僅支援 port 80 (HTTP) / 443 (HTTPS)，開發環境使用 ngrok 轉發到本機任意 port。

## 日期與時區

```kotlin
import java.time.*
import java.time.format.DateTimeFormatter

// ⚠️ ECPay 所有時間欄位皆為台灣時間（UTC+8）
private val TW_ZONE = ZoneId.of("Asia/Taipei")
private val TRADE_DATE_FMT = DateTimeFormatter.ofPattern("yyyy/MM/dd HH:mm:ss")

// MerchantTradeDate 格式：yyyy/MM/dd HH:mm:ss（非 ISO 8601）
val merchantTradeDate: String = ZonedDateTime.now(TW_ZONE).format(TRADE_DATE_FMT)
// → "2026/03/11 12:10:41"

// AES RqHeader.Timestamp：Unix 秒數（非毫秒）
// ⚠️ System.currentTimeMillis() 回傳毫秒，必須除以 1000
val timestamp: Long = Instant.now().epochSecond
```

## 環境變數

```kotlin
val config = EcpayConfig(
    merchantId = System.getenv("ECPAY_MERCHANT_ID") ?: error("Missing ECPAY_MERCHANT_ID"),
    hashKey = System.getenv("ECPAY_HASH_KEY") ?: error("Missing ECPAY_HASH_KEY"),
    hashIv = System.getenv("ECPAY_HASH_IV") ?: error("Missing ECPAY_HASH_IV"),
    baseUrl = if (System.getenv("ECPAY_ENV") == "stage")
        "https://payment-stage.ecpay.com.tw"
    else "https://payment.ecpay.com.tw",
)
```

## 日誌與監控

```kotlin
import org.slf4j.LoggerFactory

private val logger = LoggerFactory.getLogger("ecpay")

// ⚠️ 絕不記錄 HashKey / HashIV / CheckMacValue
// ✅ 記錄：API 呼叫結果、交易編號、錯誤訊息
logger.info("ECPay API 呼叫成功: MerchantTradeNo={}", merchantTradeNo)
logger.error("ECPay API 錯誤: TransCode={}, RtnCode={}", transCode, rtnCode)
```

> **日誌安全規則**：HashKey、HashIV、CheckMacValue 為機敏資料，嚴禁出現在任何日誌、錯誤回報或前端回應中。SLF4J + Logback 為 JVM 標準日誌方案。

## URL Encode 注意

```kotlin
// ⚠️ Kotlin/JVM 的 URLEncoder.encode() 在部分 JVM 不會編碼 ~ 字元
// ECPay CheckMacValue 要求 ~ 編碼為 %7e
// guides/13 的 ecpayUrlEncode 已處理此轉換（toLowerCase + ~ → %7e）
// 請直接使用 guides/13 提供的函式，勿自行實作
```

## JSON 序列化注意

```kotlin
// ⚠️ 預設 Gson 實例會 HTML 轉義 < > & = 為 \u003c 等格式
// ECPay API 不預期此轉義 — 必須禁用
// ✅ 正確：
val gson = GsonBuilder().disableHtmlEscaping().create()

// ❌ 錯誤：Gson() 預設開啟 HTML escaping
// val gson = Gson()

// 🔄 替代方案：kotlinx.serialization（Kotlin 原生，無 HTML escaping 問題）
// @Serializable data class AesRequest(...)
// Json.encodeToString(request)
```

## 單元測試模式

```kotlin
import org.junit.jupiter.api.Test
import org.junit.jupiter.api.Assertions.*

class EcpayTest {
    @Test
    fun `CMV SHA256 matches test vector`() {
        val params = mapOf(
            "MerchantID" to "3002607",
            // ... test vector params ...
        )
        assertEquals(
            "291CBA324D31FB5A4BBBFDF2CFE5D32598524753AFD4959C3BF590C5B2F57FB2",
            generateCheckMacValue(params, "pwFHCqoQZGmho4w6", "EkRm7iFT261dpevs")
        )
    }
}
```

## Linter / Formatter

```bash
# ktlint（推薦）
# 安裝：curl -sSLO https://github.com/pinterest/ktlint/releases/latest/download/ktlint
# 格式化：ktlint --format
# 或 Gradle plugin：id("org.jlleitschuh.gradle.ktlint") version "12.0.0"
```
