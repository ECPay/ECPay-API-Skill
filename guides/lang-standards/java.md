# Java — ECPay 整合程式規範

> 本檔為 AI 生成 ECPay 整合程式碼時的 Java 專屬規範。
> 加密函式：[guides/13 §Java](../13-checkmacvalue.md) + [guides/14 §Java](../14-aes-encryption.md)
> E2E 範例：[guides/24 §Java](../24-multi-language-integration.md)

## 版本與環境

- **最低版本**：Java 11+（HttpClient 內建）
- **推薦版本**：Java 17 LTS+（text blocks、sealed classes）
- **建置工具**：Maven 或 Gradle

## 推薦依賴

```xml
<!-- Maven — 大多數使用標準庫即可，僅需 JSON 處理 -->
<dependency>
  <groupId>com.google.code.gson</groupId>
  <artifactId>gson</artifactId>
  <version>2.10.1</version>
</dependency>
<!-- 或 Jackson -->
<dependency>
  <groupId>com.fasterxml.jackson.core</groupId>
  <artifactId>jackson-databind</artifactId>
  <version>2.17.0</version>
</dependency>
```

> **加密：無需第三方庫**。`javax.crypto` + `java.security` 已包含 AES-128-CBC 和 SHA-256。

## 命名慣例

```java
// 類別：PascalCase
public class EcpayPaymentService { }

// 方法 / 變數：camelCase
public String generateCheckMacValue(Map<String, String> params, String hashKey, String hashIv) { }
String merchantTradeNo = "ORDER" + System.currentTimeMillis();

// 常數：UPPER_SNAKE_CASE
public static final String ECPAY_PAYMENT_URL = "https://payment.ecpay.com.tw/Cashier/AioCheckOut/V5";

// 套件名：反向域名（全小寫）
package com.example.ecpay;

// 檔案命名 = 類別名（PascalCase.java）
// EcpayPaymentService.java, EcpayAesUtil.java
```

## 型別定義

```java
import com.google.gson.annotations.SerializedName;

public class AioParams {
    private String merchantID;        // MerchantID
    private String merchantTradeNo;
    private String merchantTradeDate; // yyyy/MM/dd HH:mm:ss
    private String paymentType = "aio";
    private String totalAmount;       // ⚠️ 整數字串
    private String returnURL;
    private String choosePayment;
    private String encryptType = "1";
    private String checkMacValue;
    // getters, setters, toMap()
}

// Java 17+ 可使用 record（不可變型別，自動生成 getter / equals / hashCode）
// record AesRequest(
//     @SerializedName("MerchantID") String merchantID,
//     @SerializedName("RqHeader") RqHeader rqHeader,
//     @SerializedName("Data") String data
// ) {}

// ⚠️ Revision 依服務：B2C 發票 = "3.0.0", B2B/票證 = "1.0.0", 站內付 2.0 = 省略（傳 null）
public class RqHeader {
    @SerializedName("Timestamp")
    private long timestamp;   // Unix 秒數（非毫秒）
    @SerializedName("Revision")
    private String revision;  // 站內付 2.0 不傳此欄位 → 設為 null，Gson 序列化時省略

    public RqHeader(long timestamp, String revision) {
        this.timestamp = timestamp;
        this.revision = revision;
    }
    public RqHeader(long timestamp) { this(timestamp, null); } // 站內付 2.0 無 Revision
}

public class AesRequest {
    @SerializedName("MerchantID")
    private String merchantID;
    @SerializedName("RqHeader")
    private RqHeader rqHeader;
    @SerializedName("Data")
    private String data;  // AES 加密後 Base64
}

public class AesResponse {
    @SerializedName("TransCode")
    private int transCode;
    @SerializedName("TransMsg")
    private String transMsg;
    @SerializedName("Data")
    private String data;
}

// ⚠️ RtnCode 為 String 型別
public class CallbackParams {
    private String rtnCode;   // "1" 表示成功，非 int
    private String merchantTradeNo;
    private String checkMacValue;
}
```

## 錯誤處理

```java
public class EcpayApiException extends RuntimeException {
    private final int transCode;
    private final String rtnCode;

    public EcpayApiException(int transCode, String rtnCode, String message) {
        super(String.format("TransCode=%d, RtnCode=%s: %s", transCode, rtnCode, message));
        this.transCode = transCode;
        this.rtnCode = rtnCode;
    }
}

public Map<String, Object> callAesApi(String url, AesRequest request, String hashKey, String hashIv) {
    HttpRequest httpReq = HttpRequest.newBuilder()
        .uri(URI.create(url))
        .header("Content-Type", "application/json")
        .POST(HttpRequest.BodyPublishers.ofString(gson.toJson(request)))
        .timeout(Duration.ofSeconds(30))
        .build();

    HttpResponse<String> resp = httpClient.send(httpReq, HttpResponse.BodyHandlers.ofString());

    if (resp.statusCode() == 403) {
        throw new EcpayApiException(-1, null, "Rate Limited — 需等待約 30 分鐘");
    }

    AesResponse result = gson.fromJson(resp.body(), AesResponse.class);

    // 雙層錯誤檢查
    if (result.getTransCode() != 1) {
        throw new EcpayApiException(result.getTransCode(), null, result.getTransMsg());
    }
    // ⚠️ aesDecrypt 回傳原始 JSON String（同 guides/14）；用 JsonObject 讀取欄位
    // 避免 Gson TypeToken<Map<String,Object>> 將整數解析為 Double（1 → 1.0，
    // String.valueOf(1.0) = "1.0" ≠ "1" 導致比對永遠失敗）
    String decryptedStr = aesDecrypt(result.getData(), hashKey, hashIv);
    JsonObject jsonData = gson.fromJson(decryptedStr, JsonObject.class);
    int rtnCode = jsonData.has("RtnCode") ? jsonData.get("RtnCode").getAsInt() : -1;
    if (rtnCode != 1) {
        throw new EcpayApiException(1, String.valueOf(rtnCode),
            jsonData.has("RtnMsg") ? jsonData.get("RtnMsg").getAsString() : "");
    }
    return gson.fromJson(decryptedStr, new com.google.gson.reflect.TypeToken<Map<String, Object>>(){}.getType());
}
```

## HTTP Client 設定

```java
// Java 11+ HttpClient（推薦全域共用實例）
private static final HttpClient httpClient = HttpClient.newBuilder()
    .connectTimeout(Duration.ofSeconds(10))
    .followRedirects(HttpClient.Redirect.NORMAL)
    .build();

// ⚠️ HttpRequest 的 timeout 是每次請求獨立設定
```

## Callback Handler 模板（Spring Boot）

```java
@RestController
public class EcpayCallbackController {

    @PostMapping(value = "/ecpay/callback",
                 consumes = MediaType.APPLICATION_FORM_URLENCODED_VALUE,
                 produces = MediaType.TEXT_PLAIN_VALUE)
    public ResponseEntity<String> handleCallback(@RequestParam Map<String, String> params) {

        // 1. Timing-safe CMV 驗證
        String receivedCmv = params.getOrDefault("CheckMacValue", "");
        params.remove("CheckMacValue");
        String expectedCmv = generateCheckMacValue(params, hashKey, hashIv);
        if (!MessageDigest.isEqual(
                receivedCmv.getBytes(java.nio.charset.StandardCharsets.UTF_8),
                expectedCmv.getBytes(java.nio.charset.StandardCharsets.UTF_8))) {
            return ResponseEntity.badRequest().body("CheckMacValue Error");
        }

        // 2. RtnCode 是字串
        if ("1".equals(params.get("RtnCode"))) {
            // 處理成功
        }

        // 3. HTTP 200 + "1|OK"
        return ResponseEntity.ok("1|OK");
    }
}
```

## Callback Handler 模板（Servlet）

```java
@Override
protected void doPost(HttpServletRequest req, HttpServletResponse resp) throws IOException {
    Map<String, String> params = new TreeMap<>();
    req.getParameterMap().forEach((k, v) -> params.put(k, v[0]));

    String receivedCmv = params.getOrDefault("CheckMacValue", "");
    params.remove("CheckMacValue");
    String expectedCmv = generateCheckMacValue(params, hashKey, hashIv);
    if (!MessageDigest.isEqual(
            receivedCmv.getBytes(java.nio.charset.StandardCharsets.UTF_8),
            expectedCmv.getBytes(java.nio.charset.StandardCharsets.UTF_8))) {
        resp.sendError(400, "CheckMacValue Error");
        return;
    }

    resp.setContentType("text/plain");
    resp.setCharacterEncoding("UTF-8");
    resp.getWriter().write("1|OK");
}
```

> ⚠️ ECPay Callback URL 僅支援 port 80 (HTTP) / 443 (HTTPS)，開發環境使用 ngrok 轉發到本機任意 port。

## 日期與時區

```java
import java.time.*;
import java.time.format.DateTimeFormatter;

// ⚠️ ECPay 所有時間欄位皆為台灣時間（UTC+8）
private static final ZoneId TW_ZONE = ZoneId.of("Asia/Taipei");
private static final DateTimeFormatter TRADE_DATE_FMT =
    DateTimeFormatter.ofPattern("yyyy/MM/dd HH:mm:ss");

// MerchantTradeDate 格式：yyyy/MM/dd HH:mm:ss（非 ISO 8601）
String merchantTradeDate = ZonedDateTime.now(TW_ZONE).format(TRADE_DATE_FMT);
// → "2026/03/11 12:10:41"

// AES RqHeader.Timestamp：Unix 秒數（非毫秒）
// ⚠️ System.currentTimeMillis() 回傳毫秒，必須除以 1000
long timestamp = Instant.now().getEpochSecond();
```

## 環境變數

```java
// 從環境變數或 application.properties 載入
String merchantId = System.getenv("ECPAY_MERCHANT_ID");
String hashKey = System.getenv("ECPAY_HASH_KEY");
String hashIv = System.getenv("ECPAY_HASH_IV");
String env = System.getenv().getOrDefault("ECPAY_ENV", "stage");

String baseUrl = "stage".equals(env)
    ? "https://payment-stage.ecpay.com.tw"
    : "https://payment.ecpay.com.tw";

// Spring Boot: application.yml
// ecpay:
//   merchant-id: ${ECPAY_MERCHANT_ID}
//   hash-key: ${ECPAY_HASH_KEY}
//   hash-iv: ${ECPAY_HASH_IV}
```

## JSON 序列化注意

```java
// Gson 有兩種轉義行為，不要混淆：
// 1. Unicode 轉義：預設不轉義（中文保持原樣，等同 Python ensure_ascii=False）
// 2. HTML 轉義：預設開啟（<, >, &, =, ' → \uXXXX）
// ⚠️ 必須禁用 HTML escaping — ECPay 不預期 \u003c 格式
Gson gson = new GsonBuilder().disableHtmlEscaping().create();
```

## 日誌與監控

```java
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

private static final Logger log = LoggerFactory.getLogger(EcpayPaymentService.class);

// ⚠️ 絕不記錄 HashKey / HashIV / CheckMacValue
// ✅ 記錄：API 呼叫結果、交易編號、錯誤訊息
log.info("ECPay API 呼叫成功: MerchantTradeNo={}", merchantTradeNo);
log.error("ECPay API 錯誤: TransCode={}, RtnCode={}", transCode, rtnCode);
```

> **日誌安全規則**：HashKey、HashIV、CheckMacValue 為機敏資料，嚴禁出現在任何日誌、錯誤回報或前端回應中。

> 💡 SLF4J 推薦搭配 Logback 作為實作。

## URL Encode 注意

```java
// ⚠️ Java 的 URLEncoder.encode() 在部分 JVM 不會編碼 ~ 字元
// ECPay CheckMacValue 要求 ~ 編碼為 %7e
// guides/13 的 ecpayUrlEncode 已處理此轉換（toLowerCase + ~ → %7e）
// 請直接使用 guides/13 提供的函式，勿自行實作
```

## 單元測試模式

```java
import static org.junit.jupiter.api.Assertions.*;
import org.junit.jupiter.api.Test;

class EcpayTest {
    @Test
    void testCmvSha256() {
        Map<String, String> params = new TreeMap<>();
        params.put("MerchantID", "3002607");
        // ... test vector params ...
        String result = generateCheckMacValue(params, "pwFHCqoQZGmho4w6", "EkRm7iFT261dpevs");
        assertEquals("291CBA324D31FB5A4BBBFDF2CFE5D32598524753AFD4959C3BF590C5B2F57FB2", result);
    }

    @Test
    void testAesRoundtrip() {
        String encrypted = aesEncrypt("{\"MerchantID\":\"2000132\"}", "ejCk326UnaZWKisg", "q9jcZX8Ib9LM8wYk");
        String decrypted = aesDecrypt(encrypted, "ejCk326UnaZWKisg", "q9jcZX8Ib9LM8wYk");
        assertTrue(decrypted.contains("2000132"));
    }
}
```

## Linter / Formatter

```bash
# 推薦使用 google-java-format
# Maven: mvn com.spotify.fmt:fmt-maven-plugin:format
# Gradle: plugins { id "com.diffplug.spotless" }
# IntelliJ: Settings → Editor → Code Style → Scheme: GoogleStyle
```
