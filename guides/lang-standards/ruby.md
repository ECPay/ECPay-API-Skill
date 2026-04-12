# Ruby — ECPay 整合程式規範

> 本檔為 AI 生成 ECPay 整合程式碼時的 Ruby 專屬規範。
> 加密函式：[guides/13 §Ruby](../13-checkmacvalue.md) + [guides/14 §Ruby](../14-aes-encryption.md)
> E2E 範例：[guides/23 §Ruby](../23-multi-language-integration.md)

## 版本與環境

- **最低版本**：Ruby 3.1+
- **推薦版本**：Ruby 3.2+
- **套件管理**：Bundler（Gemfile）
- **加密**：`openssl` 標準庫（內建，無需額外 gem）

## 推薦依賴

```ruby
# Gemfile
gem 'sinatra', '~> 3.0'   # 輕量 HTTP（或 rails）
gem 'dotenv', '~> 3.0'    # 環境變數載入
gem 'net-http'             # Ruby 3.1+ 獨立 gem

# 不需要額外加密 gem — openssl 已內建
```

## 命名慣例

```ruby
# frozen_string_literal: true
# ⚠️ 所有 .rb 檔案開頭加此 pragma — Ruby 效能最佳實踐

# 方法 / 變數：snake_case
def generate_check_mac_value(params, hash_key, hash_iv)
  # ...
end
merchant_trade_no = "ORDER#{Time.now.to_i}"

# 類別 / 模組：PascalCase
class EcpayPaymentClient
end

module Ecpay
end

# 常數：UPPER_SNAKE_CASE
ECPAY_PAYMENT_URL = 'https://payment.ecpay.com.tw/Cashier/AioCheckOut/V5'

# 檔案：snake_case.rb
# ecpay_payment.rb, ecpay_aes.rb, ecpay_callback.rb

# ⚠️ ECPay API 參數名為 PascalCase 字串 key（"MerchantID"），不可轉為 Symbol
```

## 型別定義（Struct / Data）

```ruby
# Ruby 3.2+ Data（immutable value object）
EcpayConfig = Data.define(:merchant_id, :hash_key, :hash_iv, :base_url)

# 或使用 Struct
AioParams = Struct.new(
  :merchant_id,
  :merchant_trade_no,
  :merchant_trade_date,
  :total_amount,        # ⚠️ 整數字串
  :trade_desc,
  :item_name,
  :return_url,
  :choose_payment,
  keyword_init: true,
) do
  def to_param_hash
    {
      'MerchantID'        => merchant_id,
      'MerchantTradeNo'   => merchant_trade_no,
      'MerchantTradeDate' => merchant_trade_date,
      'PaymentType'       => 'aio',
      'TotalAmount'       => total_amount,
      'TradeDesc'         => trade_desc,
      'ItemName'          => item_name,
      'ReturnURL'         => return_url,
      'ChoosePayment'     => choose_payment || 'ALL',
      'EncryptType'       => '1',
    }
  end
end

# AES-JSON 請求結構（適用：站內付 2.0、電子發票、全方位物流）
# ⚠️ Revision 依服務：B2C 發票 = "3.0.0", B2B/票證 = "1.0.0", 站內付 2.0 = 省略（不含此 key）
def build_rq_header(revision: nil)
  header = { 'Timestamp' => Time.now.to_i }
  header['Revision'] = revision unless revision.nil?
  header
end

def build_aes_request(merchant_id, encrypted_data, revision: nil)
  {
    'MerchantID' => merchant_id,
    'RqHeader'   => build_rq_header(revision: revision),
    'Data'       => encrypted_data,
  }
end

# 範例：站內付 2.0（無 Revision）
# body = build_aes_request(MERCHANT_ID, aes_encrypt(payload, HASH_KEY, HASH_IV))
# 範例：B2C 發票（Revision = "3.0.0"）
# body = build_aes_request(MERCHANT_ID, aes_encrypt(payload, HASH_KEY, HASH_IV), revision: "3.0.0")

# ⚠️ RtnCode 為字串
# params['RtnCode'] == '1'  ← 正確
# params['RtnCode'] == 1    ← 錯誤

# RBS 型別簽名（Ruby 3.1+，放在 sig/ 目錄）
# # sig/ecpay.rbs
# class EcpayPaymentClient
#   def generate_check_mac_value: (Hash[String, String], String, String) -> String
#   def aes_encrypt: (Hash[String, String], String, String) -> String
#   def aes_decrypt: (String, String, String) -> Hash[String, untyped]
# end
```

## 錯誤處理

```ruby
class EcpayApiError < StandardError
  attr_reader :trans_code, :rtn_code

  def initialize(trans_code, rtn_code, message)
    @trans_code = trans_code
    @rtn_code = rtn_code
    super("TransCode=#{trans_code}, RtnCode=#{rtn_code}: #{message}")
  end
end

def call_aes_api(url, request_body, hash_key, hash_iv)
  uri = URI(url)
  http = Net::HTTP.new(uri.host, uri.port)
  http.use_ssl = true
  http.open_timeout = 10
  http.read_timeout = 30

  req = Net::HTTP::Post.new(uri.path, { 'Content-Type' => 'application/json' })
  req.body = request_body.to_json

  resp = http.request(req)

  raise EcpayApiError.new(-1, nil, 'Rate Limited — 需等待約 30 分鐘') if resp.code == '403'
  raise EcpayApiError.new(-1, nil, "HTTP #{resp.code}") unless resp.is_a?(Net::HTTPSuccess)

  result = JSON.parse(resp.body)

  # 雙層錯誤檢查
  if result['TransCode'] != 1
    raise EcpayApiError.new(result['TransCode'], nil, result['TransMsg'])
  end

  data = aes_decrypt(result['Data'], hash_key, hash_iv)
  if data['RtnCode'].to_s != '1'
    raise EcpayApiError.new(1, data['RtnCode'], data['RtnMsg'])
  end

  data
end
```

## Callback Handler 模板（Sinatra）

```ruby
require 'sinatra'
require 'openssl'

post '/ecpay/callback' do
  params_hash = params.to_h

  # 1. Timing-safe CMV 驗證
  received_cmv = params_hash.delete('CheckMacValue')
  expected_cmv = generate_check_mac_value(params_hash, HASH_KEY, HASH_IV)
  unless OpenSSL.secure_compare(received_cmv.to_s, expected_cmv)
    halt 400, 'CheckMacValue Error'
  end

  # 2. RtnCode 是字串
  if params_hash['RtnCode'] == '1'
    # 處理成功
  end

  # 3. HTTP 200 + "1|OK"
  content_type 'text/plain'
  '1|OK'
end
```

## Callback Handler 模板（Rails）

```ruby
class EcpayCallbacksController < ApplicationController
  skip_before_action :verify_authenticity_token

  def create
    p = params.to_unsafe_h.except('controller', 'action')

    received_cmv = p.delete('CheckMacValue')
    expected_cmv = generate_check_mac_value(p, ENV['ECPAY_HASH_KEY'], ENV['ECPAY_HASH_IV'])
    unless OpenSSL.secure_compare(received_cmv.to_s, expected_cmv)
      return head :bad_request
    end

    if p['RtnCode'] == '1'
      # 處理成功
    end

    render plain: '1|OK'
  end
end
```

> ⚠️ ECPay Callback URL 僅支援 port 80 (HTTP) / 443 (HTTPS)，開發環境使用 ngrok 轉發到本機任意 port。

## 日期與時區

```ruby
require 'time'

# ⚠️ ECPay 所有時間欄位皆為台灣時間（UTC+8）

# MerchantTradeDate 格式：yyyy/MM/dd HH:mm:ss（非 ISO 8601）
merchant_trade_date = Time.now.getlocal('+08:00').strftime('%Y/%m/%d %H:%M:%S')
# → "2026/03/11 12:10:41"

# AES RqHeader.Timestamp：Unix 秒數
timestamp = Time.now.to_i  # 已為整數秒數
```

## 環境變數

```ruby
# .env（搭配 dotenv gem）
# ECPAY_MERCHANT_ID=3002607
# ECPAY_HASH_KEY=pwFHCqoQZGmho4w6
# ECPAY_HASH_IV=EkRm7iFT261dpevs
# ECPAY_ENV=stage

require 'dotenv/load'

config = EcpayConfig.new(
  merchant_id: ENV.fetch('ECPAY_MERCHANT_ID'),
  hash_key:    ENV.fetch('ECPAY_HASH_KEY'),
  hash_iv:     ENV.fetch('ECPAY_HASH_IV'),
  base_url:    ENV['ECPAY_ENV'] == 'stage'
    ? 'https://payment-stage.ecpay.com.tw'
    : 'https://payment.ecpay.com.tw',
)
```

## JSON 序列化注意

```ruby
require 'json'

# Ruby 預設：JSON.generate 不轉義 Unicode（等同 Python ensure_ascii=False）
# ⚠️ 正確：使用 String key（非 Symbol）
hash = { 'MerchantID' => '2000132', 'ItemName' => '測試商品' }
json_str = JSON.generate(hash)  # → {"MerchantID":"2000132","ItemName":"測試商品"}

# ⚠️ 錯誤：Symbol key 會導致 key 多一個冒號
hash = { MerchantID: '2000132' }
JSON.generate(hash)  # → {"MerchantID":"2000132"} — 新版 Ruby OK，但建議用 String key
```

## 日誌與監控

```ruby
require 'logger'

LOGGER = Logger.new($stdout, progname: 'ecpay')

# ⚠️ 絕不記錄 HashKey / HashIV / CheckMacValue
# ✅ 記錄：API 呼叫結果、交易編號、錯誤訊息
LOGGER.info("ECPay API 呼叫成功: MerchantTradeNo=#{merchant_trade_no}")
LOGGER.error("ECPay API 錯誤: TransCode=#{trans_code}, RtnCode=#{rtn_code}")
```

> **日誌安全規則**：HashKey、HashIV、CheckMacValue 為機敏資料，嚴禁出現在任何日誌、錯誤回報或前端回應中。

## URL Encode 注意

```ruby
# ⚠️ Ruby 的 CGI.escape() 不會編碼 ~ 字元
# ECPay CheckMacValue 要求 ~ 編碼為 %7e
# guides/13 的 ecpay_url_encode 已處理此轉換（~ → %7e）
# 請直接使用 guides/13 提供的函式，勿自行實作
```

## 單元測試模式

```ruby
# test/ecpay_test.rb — Minitest
require 'minitest/autorun'

class EcpayTest < Minitest::Test
  def test_cmv_sha256
    params = {
      'MerchantID' => '3002607',
      # ... test vector params ...
    }
    result = generate_check_mac_value(params, 'pwFHCqoQZGmho4w6', 'EkRm7iFT261dpevs')
    assert_equal '291CBA324D31FB5A4BBBFDF2CFE5D32598524753AFD4959C3BF590C5B2F57FB2', result
  end

  def test_aes_roundtrip
    data = { 'MerchantID' => '2000132', 'BarCode' => '/1234567' }
    encrypted = aes_encrypt(data, 'ejCk326UnaZWKisg', 'q9jcZX8Ib9LM8wYk')
    decrypted = aes_decrypt(encrypted, 'ejCk326UnaZWKisg', 'q9jcZX8Ib9LM8wYk')
    assert_equal '2000132', decrypted['MerchantID']
  end
end

# RSpec 替代方案（Rails 生態系標準）
# spec/ecpay_spec.rb
# RSpec.describe 'CheckMacValue' do
#   it 'matches SHA256 test vector' do
#     params = { 'MerchantID' => '3002607', ... }
#     result = generate_check_mac_value(params, 'pwFHCqoQZGmho4w6', 'EkRm7iFT261dpevs')
#     expect(result).to eq('291CBA324D31FB5A4BBBFDF2CFE5D32598524753AFD4959C3BF590C5B2F57FB2')
#   end
# end
```

## Linter / Formatter

```bash
gem install rubocop
# .rubocop.yml
# AllCops:
#   NewCops: enable
#   TargetRubyVersion: 3.2
#
# Metrics/MethodLength:
#   Max: 30
rubocop --autocorrect
```
