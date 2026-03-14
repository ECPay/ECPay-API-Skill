# C — ECPay 整合程式規範

> 本檔為 AI 生成 ECPay 整合程式碼時的 C 專屬規範。
> 加密函式：[guides/13 §C](../13-checkmacvalue.md) + [guides/14 §C](../14-aes-encryption.md)
> E2E 範例：[guides/24](../24-multi-language-integration.md)

## 版本與環境

- **標準**：C11（`_Static_assert`、`anonymous struct`）
- **推薦編譯器**：GCC 9+ / Clang 12+ / MSVC 2019+
- **加密**：OpenSSL 1.1+ 或 3.0+

## 推薦依賴

```bash
# Ubuntu/Debian
sudo apt install libssl-dev libcurl4-openssl-dev cjson

# macOS
brew install openssl curl cjson

# 編譯旗標
gcc -o ecpay ecpay.c -lssl -lcrypto -lcurl -lcjson
```

## 命名慣例

```c
// 函式：snake_case，加前綴 ecpay_
char* ecpay_generate_cmv(const char** keys, const char** values, int count,
                          const char* hash_key, const char* hash_iv);
char* ecpay_aes_encrypt(const char* json_str, const char* hash_key, const char* hash_iv);

// 結構體：snake_case + _t 後綴
typedef struct {
    char merchant_id[11];
    char hash_key[17];
    char hash_iv[17];
    char base_url[64];
} ecpay_config_t;

// 常數 / 巨集：UPPER_SNAKE_CASE
#define ECPAY_PAYMENT_URL "https://payment.ecpay.com.tw/Cashier/AioCheckOut/V5"
#define ECPAY_HASH_KEY_LEN 16
#define ECPAY_HASH_IV_LEN 16

// 列舉：ECPAY_ 前綴
typedef enum {
    ECPAY_OK = 0,
    ECPAY_ERR_HTTP,
    ECPAY_ERR_AES,
    ECPAY_ERR_CMV,
    ECPAY_ERR_RATE_LIMIT,
    ECPAY_ERR_BUSINESS,
} ecpay_error_t;

// 檔案：snake_case.c / .h
// ecpay_cmv.c, ecpay_aes.c, ecpay_http.c
```

## 型別定義

```c
// ⚠️ ECPay 所有參數為字串型別
// RtnCode 也是字串 — 用 strcmp 比較，勿用 atoi

typedef struct {
    char merchant_id[11];
    char merchant_trade_no[21];
    char merchant_trade_date[20]; // yyyy/MM/dd HH:mm:ss
    char total_amount[11];        // 整數字串
    char trade_desc[200];
    char item_name[400];
    char return_url[200];
    char choose_payment[20];
} ecpay_aio_params_t;

typedef struct {
    char rtn_code[10];            // ⚠️ 字串！用 strcmp(rtn_code, "1")
    char merchant_trade_no[21];
    char check_mac_value[65];
} ecpay_callback_params_t;
```

## 錯誤處理

```c
// C 語言錯誤處理：回傳錯誤碼 + 輸出參數

ecpay_error_t ecpay_call_aes_api(
    const char* url,
    const char* request_json,
    const char* hash_key,
    const char* hash_iv,
    char* out_data,           // 輸出：解密後的 JSON
    size_t out_data_size,
    char* out_error_msg,      // 輸出：錯誤訊息
    size_t out_error_size
) {
    // HTTP POST
    long http_code = 0;
    char* response = http_post(url, request_json, &http_code);
    if (!response) {
        snprintf(out_error_msg, out_error_size, "HTTP request failed");
        return ECPAY_ERR_HTTP;
    }
    if (http_code == 403) {
        snprintf(out_error_msg, out_error_size, "Rate Limited — retry after ~30 min");
        free(response);
        return ECPAY_ERR_RATE_LIMIT;
    }

    // 解析 TransCode
    cJSON* root = cJSON_Parse(response);
    int trans_code = cJSON_GetObjectItem(root, "TransCode")->valueint;
    if (trans_code != 1) {
        snprintf(out_error_msg, out_error_size, "TransCode=%d: %s",
                 trans_code, cJSON_GetObjectItem(root, "TransMsg")->valuestring);
        cJSON_Delete(root);
        free(response);
        return ECPAY_ERR_HTTP;
    }

    // 解密 Data → 檢查 RtnCode（可能為字串或數字）
    const char* encrypted_data = cJSON_GetObjectItem(root, "Data")->valuestring;
    char* decrypted = ecpay_aes_decrypt(encrypted_data, hash_key, hash_iv);
    cJSON* data = cJSON_Parse(decrypted);
    cJSON* rtn_code_item = cJSON_GetObjectItem(data, "RtnCode");
    char rtn_code_str[32] = {0};
    if (cJSON_IsString(rtn_code_item)) {
        strncpy(rtn_code_str, rtn_code_item->valuestring, sizeof(rtn_code_str) - 1);
    } else {
        snprintf(rtn_code_str, sizeof(rtn_code_str), "%d", rtn_code_item->valueint);
    }
    if (strcmp(rtn_code_str, "1") != 0) {
        snprintf(out_error_msg, out_error_size, "RtnCode=%s: %s",
                 rtn_code_str, cJSON_GetObjectItem(data, "RtnMsg")->valuestring);
        cJSON_Delete(data);
        free(decrypted);
        cJSON_Delete(root);
        free(response);
        return ECPAY_ERR_BUSINESS;
    }

    strncpy(out_data, decrypted, out_data_size - 1);
    out_data[out_data_size - 1] = '\0';
    cJSON_Delete(data);
    free(decrypted);
    cJSON_Delete(root);
    free(response);
    return ECPAY_OK;
}
```

## HTTP Client 設定（libcurl）

```c
#include <curl/curl.h>

// 超時設定
curl_easy_setopt(curl, CURLOPT_TIMEOUT, 30L);
curl_easy_setopt(curl, CURLOPT_CONNECTTIMEOUT, 10L);
curl_easy_setopt(curl, CURLOPT_SSL_VERIFYPEER, 1L);
curl_easy_setopt(curl, CURLOPT_USERAGENT, "ECPay-Integration/1.0");

// ⚠️ 務必驗證 SSL 憑證（正式環境）
// ⚠️ 程式啟動時呼叫一次 curl_global_init，結束時呼叫 curl_global_cleanup
// curl_global_init(CURL_GLOBAL_DEFAULT);  // main() 開頭
// curl_global_cleanup();                   // main() 結尾或 atexit
```

## CMV Timing-Safe 比較

```c
#include <openssl/crypto.h>

// 使用 CRYPTO_memcmp（OpenSSL timing-safe 比較）
// ⚠️ 必須先確認長度相等（SHA256 = 64 chars, MD5 = 32 chars），再比較內容
size_t expected_len = strlen(expected_cmv);
int cmv_verified = (strlen(received_cmv) == expected_len) &&
                   CRYPTO_memcmp(received_cmv, expected_cmv, expected_len) == 0;
// 或自行實作 constant-time compare：
int constant_time_compare(const char* a, const char* b, size_t len) {
    unsigned char result = 0;
    for (size_t i = 0; i < len; i++) {
        result |= (unsigned char)a[i] ^ (unsigned char)b[i];
    }
    return result == 0;
}
```

## 記憶體管理

```c
// ⚠️ 所有動態分配的字串必須 free
// 建議模式：呼叫者負責 free 回傳值
char* encrypted = ecpay_aes_encrypt(json_str, hash_key, hash_iv);
if (encrypted) {
    // 使用 encrypted ...
    free(encrypted);
}

// ⚠️ 敏感資料（HashKey/HashIV）用完後清零
// ⚠️ 不可使用 memset — 編譯器可能最佳化掉（dead store elimination）
#include <openssl/crypto.h>
OPENSSL_cleanse(hash_key_buf, sizeof(hash_key_buf));
```

> ⚠️ ECPay Callback URL 僅支援 port 80 (HTTP) / 443 (HTTPS)，開發環境使用 ngrok 轉發到本機任意 port。

## 日期與時區

```c
#include <time.h>
#include <stdio.h>

// ⚠️ ECPay 所有時間欄位皆為台灣時間（UTC+8）

// MerchantTradeDate 格式：yyyy/MM/dd HH:mm:ss（非 ISO 8601）
char merchant_trade_date[20];
time_t now = time(NULL);
struct tm tw_time;
// 方法 1：設定環境變數（推薦）
// setenv("TZ", "Asia/Taipei", 1); tzset();
// localtime_r(&now, &tw_time);
// 方法 2：手動加 8 小時（簡易）
now += 8 * 3600;
gmtime_r(&now, &tw_time);  // ⚠️ Windows 使用 gmtime_s(&tw_time, &now)（參數順序相反）
strftime(merchant_trade_date, sizeof(merchant_trade_date),
         "%Y/%m/%d %H:%M:%S", &tw_time);
// → "2026/03/11 12:10:41"

// AES RqHeader.Timestamp：Unix 秒數
long timestamp = (long)time(NULL); // 已為秒數
```

## 環境變數

```c
#include <stdlib.h>

ecpay_config_t load_config(void) {
    ecpay_config_t config = {0};
    const char* mid = getenv("ECPAY_MERCHANT_ID");
    const char* key = getenv("ECPAY_HASH_KEY");
    const char* iv  = getenv("ECPAY_HASH_IV");
    const char* env = getenv("ECPAY_ENV");

    if (!mid || !key || !iv) {
        fprintf(stderr, "Missing ECPAY environment variables\n");
        exit(1);
    }

    strncpy(config.merchant_id, mid, sizeof(config.merchant_id) - 1);
    strncpy(config.hash_key, key, ECPAY_HASH_KEY_LEN);
    strncpy(config.hash_iv, iv, ECPAY_HASH_IV_LEN);
    snprintf(config.base_url, sizeof(config.base_url), "%s",
             (env && strcmp(env, "stage") == 0)
                 ? "https://payment-stage.ecpay.com.tw"
                 : "https://payment.ecpay.com.tw");
    return config;
}
```

## 日誌與監控

```c
#include <stdio.h>
#include <time.h>

// C 語言使用 fprintf(stderr, ...) 或 syslog
// ⚠️ 絕不記錄 HashKey / HashIV / CheckMacValue

#define ECPAY_LOG_INFO(fmt, ...)  fprintf(stderr, "[INFO] ecpay: " fmt "\n", ##__VA_ARGS__)
#define ECPAY_LOG_ERROR(fmt, ...) fprintf(stderr, "[ERROR] ecpay: " fmt "\n", ##__VA_ARGS__)

// 用法：
// ECPAY_LOG_INFO("API 呼叫成功: MerchantTradeNo=%s", merchant_trade_no);
// ECPAY_LOG_ERROR("API 錯誤: TransCode=%d, RtnCode=%s", trans_code, rtn_code);

// POSIX 環境可使用 syslog：
// #include <syslog.h>
// openlog("ecpay", LOG_PID, LOG_USER);
// syslog(LOG_INFO, "API 呼叫成功: MerchantTradeNo=%s", merchant_trade_no);
```

> **日誌安全規則**：HashKey、HashIV、CheckMacValue 為機敏資料，嚴禁出現在任何日誌、錯誤回報或前端回應中。

## Callback Handler 模板（libmicrohttpd）

```c
// 安裝：apt install libmicrohttpd-dev / brew install libmicrohttpd
// 編譯：gcc -o callback callback.c -lmicrohttpd -lssl -lcrypto

#include <microhttpd.h>
#include <string.h>

static enum MHD_Result handle_callback(void *cls, struct MHD_Connection *connection,
    const char *url, const char *method, const char *version,
    const char *upload_data, size_t *upload_data_size, void **con_cls)
{
    // 解析 POST form data → 取得 params
    // 1. Timing-safe CMV 驗證（CRYPTO_memcmp）
    // 2. RtnCode 是字串：strcmp(rtn_code, "1") == 0
    // 3. 回傳 HTTP 200 + "1|OK"
    const char *ok = "1|OK";
    struct MHD_Response *response = MHD_create_response_from_buffer(
        strlen(ok), (void *)ok, MHD_RESPMEM_PERSISTENT);
    MHD_add_response_header(response, "Content-Type", "text/plain");
    int ret = MHD_queue_response(connection, MHD_HTTP_OK, response);
    MHD_destroy_response(response);
    return ret;
}

// 啟動：MHD_start_daemon(MHD_USE_SELECT_INTERNALLY, 8080, NULL, NULL, &handle_callback, NULL, MHD_OPTION_END);
```

> ⚠️ 完整 POST body 解析請參考 libmicrohttpd 官方文件的 `MHD_PostProcessor` 範例。

## URL Encode 注意

```c
// ⚠️ curl_easy_escape() 空格編碼為 %20 而非 +
// 且不會編碼 ~ 字元
// ECPay CheckMacValue 要求：%20 → +、~ → %7e
// guides/13 的 ecpay_url_encode 已處理這些轉換
// 請直接使用 guides/13 提供的函式，勿自行實作
```

## 單元測試模式

```c
// 使用 CUnit 或簡單的 assert
#include <assert.h>
#include <string.h>

void test_cmv_sha256(void) {
    // ... 建立 params ...
    char* result = ecpay_generate_cmv(keys, values, count,
                                       "pwFHCqoQZGmho4w6", "EkRm7iFT261dpevs");
    assert(strcmp(result, "291CBA324D31FB5A4BBBFDF2CFE5D32598524753AFD4959C3BF590C5B2F57FB2") == 0);
    free(result);
}

int main(void) {
    test_cmv_sha256();
    printf("All tests passed\n");
    return 0;
}
```

## 編譯與靜態分析

```bash
# 編譯（含警告）
gcc -Wall -Wextra -Werror -O2 -std=c11 -o ecpay ecpay.c -lssl -lcrypto -lcurl -lcjson

# 靜態分析
cppcheck --enable=all --std=c11 .
# AddressSanitizer（開發環境）
gcc -fsanitize=address -g -o ecpay ecpay.c ...
```
