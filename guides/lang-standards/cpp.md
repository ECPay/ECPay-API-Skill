# C++ — ECPay 整合程式規範

> 本檔為 AI 生成 ECPay 整合程式碼時的 C++ 專屬規範。
> 加密函式：[guides/13 §C++](../13-checkmacvalue.md) + [guides/14 §C++](../14-aes-encryption.md)
> E2E 範例：[guides/23](../23-multi-language-integration.md)

## 版本與環境

- **標準**：C++17+（`std::optional`、`std::string_view`、structured bindings）
- **推薦**：C++20（`std::format`、concepts）
- **編譯器**：GCC 10+ / Clang 12+ / MSVC 2019+
- **加密**：OpenSSL 1.1+ 或 3.0+
- **JSON**：nlohmann/json（header-only）

## 推薦依賴

```bash
# Ubuntu/Debian
sudo apt install libssl-dev libcurl4-openssl-dev nlohmann-json3-dev

# macOS
brew install openssl curl nlohmann-json

# 或使用 vcpkg / conan
vcpkg install openssl curl nlohmann-json
```

## 命名慣例

```cpp
// 命名空間：snake_case
namespace ecpay {

// 類別 / 結構體：PascalCase
class PaymentClient { };
struct AioParams { };

// 函式 / 方法：camelCase 或 snake_case（擇一一致）
std::string generateCheckMacValue(const ParamMap& params, std::string_view hashKey, std::string_view hashIv);
// 或 generate_check_mac_value（Google Style / STL Style）

// 成員變數：snake_case 或 snake_case_（Google Style 加底線）
std::string merchant_id_;
std::string hash_key_;

// 常數：kPascalCase 或 UPPER_SNAKE_CASE
constexpr const char* kPaymentUrl = "https://payment.ecpay.com.tw/Cashier/AioCheckOut/V5";

// 檔案：snake_case.cpp / .hpp
// ecpay_payment.cpp, ecpay_aes.hpp

} // namespace ecpay
```

## 型別定義

```cpp
#include <string>
#include <map>
#include <optional>
#include <nlohmann/json.hpp>

namespace ecpay {

using ParamMap = std::map<std::string, std::string>;

struct AioParams {
    std::string merchant_id;
    std::string merchant_trade_no;
    std::string merchant_trade_date;  // yyyy/MM/dd HH:mm:ss
    std::string total_amount;         // ⚠️ 整數字串
    std::string trade_desc;
    std::string item_name;
    std::string return_url;
    std::string choose_payment = "ALL";

    ParamMap toParamMap() const {
        return {
            {"MerchantID", merchant_id},
            {"MerchantTradeNo", merchant_trade_no},
            {"MerchantTradeDate", merchant_trade_date},
            {"PaymentType", "aio"},
            {"TotalAmount", total_amount},
            {"TradeDesc", trade_desc},
            {"ItemName", item_name},
            {"ReturnURL", return_url},
            {"ChoosePayment", choose_payment},
            {"EncryptType", "1"},
        };
    }
};

// AES-JSON 請求以 nlohmann::json 直接組裝：
// { "MerchantID": "...", "RqHeader": { "Timestamp": ..., "Revision": "..." }, "Data": "..." }
// RqHeader.Revision 依服務填入（詳見 guides/14 §使用場景 / guides/19 §2.1 AES-JSON）：
//   發票 B2C: "3.0.0" | 發票 B2B: "1.0.0"（且必填 RqID UUID v4）
//   全方位物流 / 跨境物流: "1.0.0"
//   站內付 2.0 / 幕後授權 / 幕後取號 / 電子票證 / 直播收款: 不使用（省略 key）
// ⚠️ 把電子發票的 "3.0.0" 加到站內付 2.0 請求會導致 TransCode ≠ 1

struct AesResponse {
    int TransCode;
    std::string TransMsg;
    std::string Data;

    NLOHMANN_DEFINE_TYPE_INTRUSIVE(AesResponse, TransCode, TransMsg, Data)
};

// ⚠️ RtnCode 為 std::string
struct CallbackParams {
    std::string rtn_code;             // "1" 非 int — 用 == "1" 比較
    std::string merchant_trade_no;
    std::string check_mac_value;
};

struct Config {
    std::string merchant_id;
    std::string hash_key;
    std::string hash_iv;
    std::string base_url;
};

} // namespace ecpay
```

## 錯誤處理

```cpp
#include <stdexcept>
#include <string>

namespace ecpay {

class ApiError : public std::runtime_error {
public:
    int trans_code;
    std::string rtn_code;

    ApiError(int tc, const std::string& rc, const std::string& msg)
        : std::runtime_error("TransCode=" + std::to_string(tc) + ", RtnCode=" + rc + ": " + msg),
          trans_code(tc), rtn_code(rc) {}
};

class RateLimitError : public std::runtime_error {
public:
    RateLimitError() : std::runtime_error("Rate Limited (403) — retry after ~30 min") {}
};

nlohmann::json callAesApi(
    const std::string& url,
    const nlohmann::json& request,
    const std::string& hashKey,
    const std::string& hashIv
) {
    auto [httpCode, body] = httpPost(url, request.dump());

    if (httpCode == 403) throw RateLimitError();
    if (httpCode != 200) throw ApiError(-1, "", "HTTP " + std::to_string(httpCode));

    auto result = nlohmann::json::parse(body);
    int transCode = result["TransCode"].get<int>();

    // 雙層錯誤檢查
    if (transCode != 1) {
        throw ApiError(transCode, "", result["TransMsg"].get<std::string>());
    }

    std::string decrypted = ecpayAesDecrypt(result["Data"].get<std::string>(), hashKey, hashIv);
    auto data = nlohmann::json::parse(decrypted);

    std::string rtnCode = data["RtnCode"].is_string()
        ? data["RtnCode"].get<std::string>()
        : std::to_string(data["RtnCode"].get<int>());

    if (rtnCode != "1") {
        throw ApiError(1, rtnCode, data.value("RtnMsg", ""));
    }
    return data;
}

} // namespace ecpay
```

## HTTP Client 設定（libcurl RAII）

> **主要推薦**：libcurl（C/C++ 通用，guides/13 及 guides/14 的 C++ 區段均使用 OpenSSL + libcurl）。
> **替代方案**：[cpr](https://github.com/libcpr/cpr)（C++ 封裝 libcurl，API 更簡潔）— guides/23 C++ CMake 範例使用 cpr。兩者皆可，同一專案內保持一致。

```cpp
#include <curl/curl.h>
#include <memory>

// RAII wrapper for CURL handle
struct CurlDeleter {
    void operator()(CURL* curl) const { curl_easy_cleanup(curl); }
};
using CurlPtr = std::unique_ptr<CURL, CurlDeleter>;

std::pair<long, std::string> httpPost(const std::string& url, const std::string& body) {
    CurlPtr curl(curl_easy_init());
    curl_easy_setopt(curl.get(), CURLOPT_URL, url.c_str());
    curl_easy_setopt(curl.get(), CURLOPT_POSTFIELDS, body.c_str());
    curl_easy_setopt(curl.get(), CURLOPT_TIMEOUT, 30L);
    curl_easy_setopt(curl.get(), CURLOPT_CONNECTTIMEOUT, 10L);
    std::string response;
    curl_easy_setopt(curl.get(), CURLOPT_WRITEFUNCTION,
        +[](char* ptr, size_t size, size_t nmemb, std::string* data) -> size_t {
            data->append(ptr, size * nmemb);
            return size * nmemb;
        });
    curl_easy_setopt(curl.get(), CURLOPT_WRITEDATA, &response);

    struct curl_slist* headers = nullptr;
    headers = curl_slist_append(headers, "Content-Type: application/json");  // AES-JSON 協定用 JSON
    curl_easy_setopt(curl.get(), CURLOPT_HTTPHEADER, headers);

    CURLcode res = curl_easy_perform(curl.get());
    curl_slist_free_all(headers);
    if (res != CURLE_OK) throw std::runtime_error(curl_easy_strerror(res));

    long httpCode = 0;
    curl_easy_getinfo(curl.get(), CURLINFO_RESPONSE_CODE, &httpCode);
    return {httpCode, response};
}
```

## CMV Timing-Safe 比較

```cpp
#include <openssl/crypto.h>

// OpenSSL CRYPTO_memcmp（timing-safe）
bool verifyCmv(const std::string& received, const std::string& expected) {
    if (received.size() != expected.size()) return false;
    return CRYPTO_memcmp(received.data(), expected.data(), received.size()) == 0;
}
```

## 記憶體與資源管理

```cpp
// ⚠️ 使用 RAII — 所有資源以 smart pointer 或 scope guard 管理
// ⚠️ 敏感資料清零
#include <openssl/crypto.h>

void secureZero(std::string& s) {
    OPENSSL_cleanse(s.data(), s.size());
}

// ⚠️ ecpayAesDecrypt 回傳已解密字串 — 不可再次呼叫 urlDecode
// 正確用法：
auto data = nlohmann::json::parse(ecpayAesDecrypt(encrypted, hashKey, hashIv));
```

> ⚠️ ECPay Callback URL 僅支援 port 80 (HTTP) / 443 (HTTPS)，開發環境使用 ngrok 轉發到本機任意 port。

## 日期與時區

```cpp
#include <chrono>
#include <ctime>
#include <iomanip>
#include <sstream>

// ⚠️ ECPay 所有時間欄位皆為台灣時間（UTC+8）

// MerchantTradeDate 格式：yyyy/MM/dd HH:mm:ss（非 ISO 8601）
std::string getMerchantTradeDate() {
    auto now = std::chrono::system_clock::now();
    // 加 8 小時偏移
    now += std::chrono::hours(8);
    auto time_t_now = std::chrono::system_clock::to_time_t(now);
    std::tm tm_buf{};
    gmtime_r(&time_t_now, &tm_buf);  // Windows: gmtime_s(&tm_buf, &time_t_now)
    std::ostringstream oss;
    oss << std::put_time(&tm_buf, "%Y/%m/%d %H:%M:%S");
    return oss.str();
    // → "2026/03/11 12:10:41"
}

// AES RqHeader.Timestamp：Unix 秒數
auto timestamp = std::chrono::duration_cast<std::chrono::seconds>(
    std::chrono::system_clock::now().time_since_epoch()
).count();
```

## 環境變數

```cpp
#include <cstdlib>
#include <stdexcept>

ecpay::Config loadConfig() {
    auto getEnv = [](const char* name) -> std::string {
        const char* val = std::getenv(name);
        if (!val) throw std::runtime_error(std::string("Missing ") + name);
        return val;
    };

    std::string env = std::getenv("ECPAY_ENV") ? std::getenv("ECPAY_ENV") : "stage";
    return {
        getEnv("ECPAY_MERCHANT_ID"),
        getEnv("ECPAY_HASH_KEY"),
        getEnv("ECPAY_HASH_IV"),
        env == "stage"
            ? "https://payment-stage.ecpay.com.tw"
            : "https://payment.ecpay.com.tw",
    };
}
```

## 日誌與監控

```cpp
// 推薦 spdlog（高效能結構化日誌，header-only 可選）
// vcpkg install spdlog / apt install libspdlog-dev
#include <spdlog/spdlog.h>

// ⚠️ 絕不記錄 HashKey / HashIV / CheckMacValue
// ✅ 記錄：API 呼叫結果、交易編號、錯誤訊息
spdlog::info("ECPay API 呼叫成功: MerchantTradeNo={}", merchantTradeNo);
spdlog::error("ECPay API 錯誤: TransCode={}, RtnCode={}", transCode, rtnCode);
```

> **日誌安全規則**：HashKey、HashIV、CheckMacValue 為機敏資料，嚴禁出現在任何日誌、錯誤回報或前端回應中。

## Callback Handler 模板（cpp-httplib）

```cpp
// cpp-httplib：header-only HTTP server（https://github.com/yhirose/cpp-httplib）
#include <httplib.h>

httplib::Server svr;
svr.Post("/ecpay/callback", [&](const httplib::Request& req, httplib::Response& res) {
    auto params = req.params;  // multimap<string, string>

    // 1. Timing-safe CMV 驗證
    auto cmvIt = params.find("CheckMacValue");
    if (cmvIt == params.end()) {
        res.status = 400;
        res.set_content("Missing CheckMacValue", "text/plain");
        return;
    }
    auto receivedCmv = cmvIt->second;
    params.erase("CheckMacValue");
    ecpay::ParamMap paramMap(params.begin(), params.end());
    auto expectedCmv = ecpay::generateCheckMacValue(paramMap, hashKey, hashIv);
    if (!ecpay::verifyCmv(receivedCmv, expectedCmv)) {
        res.status = 400;
        res.set_content("CheckMacValue Error", "text/plain");
        return;
    }

    // 2. RtnCode 是字串
    auto rtnIt = params.find("RtnCode");
    if (rtnIt != params.end() && rtnIt->second == "1") {
        // 處理成功
    }

    // 3. HTTP 200 + "1|OK"
    res.set_content("1|OK", "text/plain");
});
svr.listen("0.0.0.0", 8080);
```

## URL Encode 注意

```cpp
// ⚠️ C++ 無標準 URL encode — 使用 Boost.URL 或手動實作時注意：
// 空格必須編碼為 +（非 %20）、~ 必須編碼為 %7e
// guides/13 的 ecpayUrlEncode 已處理這些轉換
// 請直接使用 guides/13 提供的函式，勿自行實作
```

## 單元測試模式

```cpp
// 使用 Google Test
#include <gtest/gtest.h>

TEST(EcpayTest, CmvSha256) {
    ecpay::ParamMap params = {
        {"MerchantID", "3002607"},
        // ... test vector params ...
    };
    auto result = ecpay::generateCheckMacValue(params, "pwFHCqoQZGmho4w6", "EkRm7iFT261dpevs");
    EXPECT_EQ(result, "291CBA324D31FB5A4BBBFDF2CFE5D32598524753AFD4959C3BF590C5B2F57FB2");
}

TEST(EcpayTest, AesRoundtrip) {
    nlohmann::json data = {{"MerchantID", "2000132"}, {"BarCode", "/1234567"}};
    auto encrypted = ecpay::ecpayAesEncrypt(data.dump(), "ejCk326UnaZWKisg", "q9jcZX8Ib9LM8wYk");
    auto decrypted = ecpay::ecpayAesDecrypt(encrypted, "ejCk326UnaZWKisg", "q9jcZX8Ib9LM8wYk");
    auto parsed = nlohmann::json::parse(decrypted);
    EXPECT_EQ(parsed["MerchantID"], "2000132");
}
```

## 編譯與靜態分析

```bash
# CMake 建置
cmake -B build -DCMAKE_BUILD_TYPE=Release
cmake --build build

# 靜態分析
clang-tidy -checks='*,-fuchsia-*,-llvmlibc-*' ecpay_*.cpp
cppcheck --enable=all --std=c++17 .

# AddressSanitizer（開發環境）
g++ -fsanitize=address -g -o ecpay ecpay.cpp -lssl -lcrypto -lcurl

# Formatter
clang-format -i ecpay_*.cpp ecpay_*.hpp
```
