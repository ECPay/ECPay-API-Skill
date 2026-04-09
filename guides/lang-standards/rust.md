# Rust — ECPay 整合程式規範

> 本檔為 AI 生成 ECPay 整合程式碼時的 Rust 專屬規範。
> 加密函式：[guides/13 §Rust](../13-checkmacvalue.md) + [guides/14 §Rust](../14-aes-encryption.md)
> E2E 範例：[guides/24 §Rust](../24-multi-language-integration.md)

## 版本與環境

- **最低版本**：Rust 1.70+（stable）
- **推薦版本**：最新 stable
- **建置工具**：Cargo

## 推薦依賴

```toml
# Cargo.toml
[dependencies]
aes = "0.8"
cbc = { version = "0.1", features = ["alloc"] }
sha2 = "0.10"
base64 = "0.22"
urlencoding = "2.1"
reqwest = { version = "0.12", features = ["json"] }
tokio = { version = "1", features = ["full"] }
serde = { version = "1", features = ["derive"] }
serde_json = "1"
subtle = "2.5"
dotenv = "0.15"
chrono = { version = "0.4", features = ["clock"] }
once_cell = "1.19"
thiserror = "1.0"
```

## 命名慣例

```rust
// 函式 / 變數 / 模組：snake_case
fn generate_check_mac_value(params: &BTreeMap<String, String>, hash_key: &str, hash_iv: &str) -> String { }
let merchant_trade_no = format!("ORDER{}", chrono::Utc::now().timestamp());

// 結構體 / 列舉 / Trait：PascalCase
struct EcpayPaymentClient { }
enum PaymentMethod { Credit, Atm, Cvs }

// 常數：UPPER_SNAKE_CASE
const ECPAY_PAYMENT_URL: &str = "https://payment.ecpay.com.tw/Cashier/AioCheckOut/V5";

// 模組：snake_case
mod ecpay_aes;
mod check_mac_value;

// 檔案：snake_case.rs
// ecpay_payment.rs, ecpay_aes.rs
```

## 型別定義

```rust
use serde::{Deserialize, Serialize};

#[derive(Debug, Serialize)]
#[serde(rename_all = "PascalCase")]
pub struct AioParams {
    #[serde(rename = "MerchantID")]
    pub merchant_id: String,
    pub merchant_trade_no: String,
    pub merchant_trade_date: String, // yyyy/MM/dd HH:mm:ss
    pub payment_type: String,        // "aio"
    pub total_amount: String,        // ⚠️ 整數字串
    pub trade_desc: String,
    pub item_name: String,
    #[serde(rename = "ReturnURL")]
    pub return_url: String,
    pub choose_payment: String,
    pub encrypt_type: String,        // "1"
    #[serde(skip_serializing_if = "Option::is_none")]
    pub check_mac_value: Option<String>,
}

#[derive(Debug, Serialize)]
pub struct AesRequest {
    #[serde(rename = "MerchantID")]
    pub merchant_id: String,
    #[serde(rename = "RqHeader")]
    pub rq_header: RqHeader,
    #[serde(rename = "Data")]
    pub data: String,
}

#[derive(Debug, Serialize)]
pub struct RqHeader {
    #[serde(rename = "Timestamp")]
    pub timestamp: i64,
    /// 依服務設定：B2C 發票 = "3.0.0"，B2B/票證 = "1.0.0"，站內付 2.0 = 省略（None）
    #[serde(rename = "Revision", skip_serializing_if = "Option::is_none")]
    pub revision: Option<String>,
}

#[derive(Debug, Deserialize)]
pub struct AesResponse {
    #[serde(rename = "TransCode")]
    pub trans_code: i32,
    #[serde(rename = "TransMsg")]
    pub trans_msg: String,
    #[serde(rename = "Data")]
    pub data: String,
}

// ⚠️ RtnCode 型別依協議：CMV 服務（AIO/物流）→ String；AES-JSON 服務（ECPG/發票）→ i32（整數）
// 以下範例為 CMV 服務（AIO callback）
#[derive(Debug, Deserialize)]
pub struct CallbackParams {
    #[serde(rename = "RtnCode")]
    pub rtn_code: String,             // AIO/物流 callback 為字串 "1"；ECPG/發票解密後為整數（應改 i32）
    #[serde(rename = "MerchantTradeNo")]
    pub merchant_trade_no: String,
    #[serde(rename = "CheckMacValue")]
    pub check_mac_value: String,
}

pub struct EcpayConfig {
    pub merchant_id: String,
    pub hash_key: String,
    pub hash_iv: String,
    pub base_url: String,
}
```

## 錯誤處理

```rust
use thiserror::Error;

#[derive(Error, Debug)]
pub enum EcpayError {
    #[error("HTTP error: {0}")]
    Http(#[from] reqwest::Error),

    #[error("Rate limited (403) — retry after ~30 min")]
    RateLimited,

    #[error("TransCode={trans_code}: {message}")]
    TransportError { trans_code: i32, message: String },

    #[error("RtnCode={rtn_code}: {message}")]
    BusinessError { rtn_code: String, message: String },

    #[error("AES decrypt error: {0}")]
    AesError(String),

    #[error("CheckMacValue mismatch")]
    CmvMismatch,
}
```

## HTTP Client

```rust
use once_cell::sync::Lazy;

// ⚠️ reqwest::Client 內建連線池，應全域共用，勿每次請求建立新實例
// reqwest::Client 為 Send + Sync，可安全跨 async task 共用
static HTTP_CLIENT: Lazy<reqwest::Client> = Lazy::new(|| {
    reqwest::Client::builder()
        .timeout(std::time::Duration::from_secs(30))
        .build()
        .expect("Failed to create HTTP client")
});

pub async fn call_aes_api(
    client: &reqwest::Client,  // 接收共用 Client 或使用 &*HTTP_CLIENT
    url: &str,
    request: &AesRequest,
    hash_key: &str,
    hash_iv: &str,
) -> Result<serde_json::Value, EcpayError> {
    let resp = client.post(url).json(request).send().await?;

    if resp.status() == 403 {
        return Err(EcpayError::RateLimited);
    }

    let result: AesResponse = resp.json().await?;

    // 雙層錯誤檢查
    if result.trans_code != 1 {
        return Err(EcpayError::TransportError {
            trans_code: result.trans_code,
            message: result.trans_msg,
        });
    }

    let data = aes_decrypt(&result.data, hash_key, hash_iv)?;
    // ⚠️ AES-JSON 服務解密後 RtnCode 為整數 1（serde_json::Value::Number）
    // as_str() 對 Number 值永遠返回 None，必須用 as_i64() 比較
    if data["RtnCode"].as_i64() != Some(1) {
        return Err(EcpayError::BusinessError {
            rtn_code: data["RtnCode"].to_string(),
            message: data["RtnMsg"].as_str().unwrap_or("").to_string(),
        });
    }

    Ok(data)
}
```

## Callback Handler 模板（Axum）

```rust
use axum::{extract::Form, http::StatusCode, response::IntoResponse};
use subtle::ConstantTimeEq;

async fn ecpay_callback(Form(mut params): Form<BTreeMap<String, String>>) -> impl IntoResponse {
    // 1. Timing-safe CMV 驗證
    let received_cmv = params.remove("CheckMacValue").unwrap_or_default();
    let expected_cmv = generate_check_mac_value(&params, &HASH_KEY, &HASH_IV);

    if received_cmv.as_bytes().ct_eq(expected_cmv.as_bytes()).unwrap_u8() != 1 {
        return (StatusCode::BAD_REQUEST, "CheckMacValue Error");
    }

    // 2. RtnCode 是字串
    if params.get("RtnCode").map(|s| s.as_str()) == Some("1") {
        // 處理成功
    }

    // 3. HTTP 200 + "1|OK"
    (StatusCode::OK, "1|OK")
}
```

> ⚠️ ECPay Callback URL 僅支援 port 80 (HTTP) / 443 (HTTPS)，開發環境使用 ngrok 轉發到本機任意 port。

### AES-JSON Callback Handler（ECPG / 發票 / 物流 v2）

```rust
// AES-JSON 服務的 Notify 回調：Content-Type: application/json，使用 Json 提取器
use axum::{extract::Json as AxumJson, http::StatusCode, response::IntoResponse};

#[derive(Deserialize)]
struct EcpayAesCallback {
    #[serde(rename = "TransCode")]
    trans_code: i32,
    #[serde(rename = "Data")]
    data: String,
}

async fn ecpay_aes_notify(
    AxumJson(payload): AxumJson<EcpayAesCallback>,
) -> impl IntoResponse {
    // 即使出錯也要回應 1|OK（HTTP 200 純文字），否則 ECPay 會重試
    if payload.trans_code != 1 {
        tracing::error!(trans_code = payload.trans_code, "ECPay AES TransCode error");
        return (StatusCode::OK, "1|OK").into_response();
    }

    let data = match aes_decrypt(&payload.data, &HASH_KEY, &HASH_IV) {
        Ok(d) => d,
        Err(e) => {
            tracing::error!("AES decrypt failed: {}", e);
            return (StatusCode::OK, "1|OK").into_response();
        }
    };

    // ⚠️ AES-JSON 解密後 RtnCode 為整數（as_i64()），非字串
    if data["RtnCode"].as_i64() == Some(1) {
        let trade_no = data["MerchantTradeNo"].as_str().unwrap_or("");
        // 更新訂單（MerchantTradeNo 冪等，避免重複處理）
        tracing::info!(merchant_trade_no = trade_no, "Payment success");
    }

    (StatusCode::OK, "1|OK").into_response()
}
```

## 日期與時區

```rust
use chrono::{Utc, FixedOffset};

// ⚠️ ECPay 所有時間欄位皆為台灣時間（UTC+8）
let tw_offset = FixedOffset::east_opt(8 * 3600).unwrap();

// MerchantTradeDate 格式：yyyy/MM/dd HH:mm:ss（非 ISO 8601）
let merchant_trade_date = Utc::now()
    .with_timezone(&tw_offset)
    .format("%Y/%m/%d %H:%M:%S")
    .to_string();
// → "2026/03/11 12:10:41"

// AES RqHeader.Timestamp：Unix 秒數
let timestamp = Utc::now().timestamp(); // i64，已為秒數
```

## 環境變數

```rust
use std::env;

fn load_config() -> EcpayConfig {
    dotenv::dotenv().ok();
    let env = env::var("ECPAY_ENV").unwrap_or_else(|_| "stage".to_string());
    EcpayConfig {
        merchant_id: env::var("ECPAY_MERCHANT_ID").expect("ECPAY_MERCHANT_ID required"),
        hash_key: env::var("ECPAY_HASH_KEY").expect("ECPAY_HASH_KEY required"),
        hash_iv: env::var("ECPAY_HASH_IV").expect("ECPAY_HASH_IV required"),
        base_url: if env == "stage" {
            "https://payment-stage.ecpay.com.tw".to_string()
        } else {
            "https://payment.ecpay.com.tw".to_string()
        },
    }
}
```

## JSON 序列化注意

```rust
// serde_json::to_string 預設保留 Unicode（不轉義為 \uXXXX）
// ⚠️ serde_json 預設不會轉義 < > &（與 Go 不同）— 符合 ECPay 預期
// ⚠️ BTreeMap 自動按 key 排序（CMV 不需要，但 AES 不依賴排序所以無害）
```

## 日誌與監控

```rust
// 推薦 tracing（tokio 生態系標準，支援結構化日誌）
// Cargo.toml: tracing = "0.1", tracing-subscriber = "0.3"
use tracing::{info, error};

// ⚠️ 絕不記錄 HashKey / HashIV / CheckMacValue
// ✅ 記錄：API 呼叫結果、交易編號、錯誤訊息
info!(merchant_trade_no = %merchant_trade_no, "ECPay API 呼叫成功");
error!(trans_code = result.trans_code, rtn_code = %rtn_code, "ECPay API 錯誤");
```

> **日誌安全規則**：HashKey、HashIV、CheckMacValue 為機敏資料，嚴禁出現在任何日誌、錯誤回報或前端回應中。

## URL Encode 注意

```rust
// ⚠️ Rust 的 urlencoding::encode() 空格編碼為 %20 而非 +
// 且不會編碼 ~ 字元
// ECPay CheckMacValue 要求：%20 → +、~ → %7e
// guides/13 的 ecpay_url_encode 已處理這些轉換
// 請直接使用 guides/13 提供的函式，勿自行實作
```

> **替代方案**：`form_urlencoded` crate（url crate 生態）將空格編碼為 `+` 且自動編碼 `~`→`%7E`，
> 行為更接近 PHP `urlencode`，可減少手動替換步驟。guides/24 E2E 範例使用 `form_urlencoded`；
> guides/13、guides/14 的加密函式使用 `urlencoding`。兩者皆可，但同一專案內應保持一致。

### aes_url_encode 完整實作（AES-JSON 服務）

```rust
// AES-JSON 服務的 URL Encode（percent-encoding crate）
// CMV 服務的 ecpay_url_encode 在 guides/13；此為 AES 服務內層 Data 加密前的字串處理
// Cargo.toml: percent-encoding = "2.3"
use percent_encoding::{utf8_percent_encode, AsciiSet, CONTROLS};

// 在 RFC3986 unreserved 字元之外，額外強制編碼 !*'()~ 以符合 ECPay 規範
const AES_ENCODE_SET: &AsciiSet = &CONTROLS
    .add(b' ').add(b'"').add(b'#').add(b'%').add(b'<').add(b'>')
    .add(b'\\').add(b'^').add(b'`').add(b'{').add(b'|').add(b'}')
    .add(b'!').add(b'*').add(b'\'').add(b'(').add(b')').add(b'~');

/// AES-JSON 服務用：urlencode，空格 → +，~ → %7E（大寫）
pub fn aes_url_encode(input: &str) -> String {
    utf8_percent_encode(input, AES_ENCODE_SET)
        .to_string()
        .replace("%20", "+")
        .replace("~", "%7E")
}
```

## 單元測試模式

```rust
#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn test_cmv_sha256() {
        let mut params = BTreeMap::new();
        params.insert("MerchantID".to_string(), "3002607".to_string());
        // ... test vector params ...
        let result = generate_check_mac_value(&params, "pwFHCqoQZGmho4w6", "EkRm7iFT261dpevs");
        assert_eq!(result, "291CBA324D31FB5A4BBBFDF2CFE5D32598524753AFD4959C3BF590C5B2F57FB2");
    }

    #[test]
    fn test_aes_roundtrip() {
        let data = serde_json::json!({"MerchantID": "2000132", "BarCode": "/1234567"});
        let encrypted = aes_encrypt(&data, "ejCk326UnaZWKisg", "q9jcZX8Ib9LM8wYk").unwrap();
        let decrypted = aes_decrypt(&encrypted, "ejCk326UnaZWKisg", "q9jcZX8Ib9LM8wYk").unwrap();
        assert_eq!(decrypted["MerchantID"], "2000132");
    }
}
```

```bash
cargo test
cargo clippy -- -D warnings
cargo fmt --check
```
