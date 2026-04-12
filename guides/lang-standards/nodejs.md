# Node.js — ECPay 整合程式規範

> 本檔為 AI 生成 ECPay 整合程式碼時的 Node.js 專屬規範。
> 加密函式：[guides/13 §Node.js](../13-checkmacvalue.md) + [guides/14 §Node.js](../14-aes-encryption.md)
> E2E 範例：[guides/00 §Quick Start](../00-getting-started.md) + [guides/23](../23-multi-language-integration.md)

## 版本與環境

- **最低版本**：Node.js 18+（原生 `fetch`、穩定 `crypto`）
- **推薦版本**：Node.js 20 LTS+
- **套件管理**：`npm`（package.json）或 `pnpm`

## 推薦依賴

```json
{
  "dependencies": {
    "express": "^4.18",
    "dotenv": "^16.0"
  }
}
```

> **內建 crypto 即可**：Node.js `crypto` 模組已包含 AES-128-CBC 和 SHA256，無需第三方加密庫。

## 命名慣例

```javascript
// 函式 / 變數：camelCase
function generateCheckMacValue(params, hashKey, hashIv) { }
const merchantTradeNo = `ORDER${Date.now()}`;

// 類別：PascalCase
class EcpayPaymentClient { }

// 常數：UPPER_SNAKE_CASE
const ECPAY_PAYMENT_URL = 'https://payment.ecpay.com.tw/Cashier/AioCheckOut/V5';

// 檔案：kebab-case.js 或 camelCase.js
// ecpay-payment.js, ecpayAes.js, ecpay-callback.js

// ⚠️ ECPay 參數名保持 PascalCase（MerchantID, HashKey）— 這是 API 規格，不可轉換
```

```javascript
// ⚠️ ESM 語法（Node.js 20+ / package.json 中 "type": "module"）
// import crypto from 'node:crypto';
// import express from 'express';
// export function generateCheckMacValue(...) { }
// 本文件範例以 CommonJS 為主，ESM 使用者請自行替換 require → import
```

## 型別定義（JSDoc）

```javascript
/**
 * @typedef {Object} AioParams
 * @property {string} MerchantID
 * @property {string} MerchantTradeNo
 * @property {string} MerchantTradeDate - yyyy/MM/dd HH:mm:ss
 * @property {'aio'} PaymentType
 * @property {string} TotalAmount - 整數字串
 * @property {string} ReturnURL
 * @property {string} ChoosePayment
 * @property {'1'} EncryptType
 * @property {string} CheckMacValue
 */

/**
 * @typedef {Object} AesRequest
 * @property {string} MerchantID
 * @property {{Timestamp: number, Revision?: string}} RqHeader - Revision 視服務而定：B2C 發票="3.0.0", B2B/ECTicket="1.0.0", 站內付2.0=省略
 * @property {string} Data - AES 加密後 Base64
 */

/**
 * @typedef {Object} CallbackParams
 * @property {string} RtnCode - ⚠️ 字串，用 === '1' 比較
 * @property {string} MerchantTradeNo
 * @property {string} CheckMacValue
 */
```

## 錯誤處理

```javascript
class EcpayApiError extends Error {
  constructor(transCode, rtnCode, message) {
    super(`TransCode=${transCode}, RtnCode=${rtnCode}: ${message}`);
    this.transCode = transCode;
    this.rtnCode = rtnCode;
  }
}

async function callAesApi(url, requestBody, hashKey, hashIv) {
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 30000);

  try {
    const resp = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(requestBody),
      signal: controller.signal,
    });

    if (resp.status === 403) {
      throw new EcpayApiError(-1, null, 'Rate Limited — 需等待約 30 分鐘');
    }
    if (!resp.ok) {
      throw new EcpayApiError(-1, null, `HTTP ${resp.status}`);
    }

    const result = await resp.json();

    // 雙層錯誤檢查
    if (result.TransCode !== 1) {
      throw new EcpayApiError(result.TransCode, null, result.TransMsg);
    }
    const data = aesDecrypt(result.Data, hashKey, hashIv);
    // RtnCode 型別依協定不同：AIO/物流 Callback 為字串 "1"；AES-JSON 解密後為整數 1
    // 使用 String() 轉換確保跨協定相容
    if (String(data.RtnCode) !== '1') {
      throw new EcpayApiError(1, data.RtnCode, data.RtnMsg);
    }
    return data;
  } finally {
    clearTimeout(timeout);
  }
}
```

## HTTP Client 設定

```javascript
// Node.js 18+ 內建 fetch，適合大部分場景
// 若需連線池管理，使用 undici（Node.js 底層 HTTP 引擎）
// Timestamp 生成範例見「日期與時區」章節
```

## Callback Handler 模板

```javascript
const express = require('express');
const crypto = require('crypto');
const app = express();

app.use(express.urlencoded({ extended: false }));

app.post('/ecpay/callback', (req, res) => {
  const params = { ...req.body };

  // 1. 驗證 CheckMacValue（timing-safe）
  const receivedCmv = params.CheckMacValue || '';
  delete params.CheckMacValue;
  const expectedCmv = generateCheckMacValue(params, HASH_KEY, HASH_IV);

  // ⚠️ timingSafeEqual 在長度不同時會 throw ERR_CRYPTO_TIMING_SAFE_EQUAL_LENGTH — 必須先檢查長度
  const receivedBuf = Buffer.from(receivedCmv);
  const expectedBuf = Buffer.from(expectedCmv);
  if (receivedBuf.length !== expectedBuf.length ||
      !crypto.timingSafeEqual(receivedBuf, expectedBuf)) {
    return res.status(400).send('CheckMacValue Error');
  }

  // 2. 冪等性檢查
  // if (await isOrderProcessed(params.MerchantTradeNo)) { ... }

  // 3. 業務邏輯（RtnCode 是字串）
  if (params.RtnCode === '1') {
    // 處理付款成功
  }

  // 4. 必須回傳 HTTP 200 + 純文字 "1|OK"
  res.status(200).type('text/plain').send('1|OK');
});

// ⚠️ ECPay Callback URL 僅支援 port 80 (HTTP) / 443 (HTTPS)
// 開發環境使用 ngrok 轉發到本機任意 port
```

## 日期與時區

```javascript
// ⚠️ ECPay 所有時間欄位皆為台灣時間（UTC+8）

// MerchantTradeDate 格式：yyyy/MM/dd HH:mm:ss（非 ISO 8601）
function getMerchantTradeDate() {
  return new Date().toLocaleString('sv-SE', {
    timeZone: 'Asia/Taipei',
    year: 'numeric', month: '2-digit', day: '2-digit',
    hour: '2-digit', minute: '2-digit', second: '2-digit',
    hour12: false,
  }).replace(/-/g, '/');
  // → "2026/03/11 12:10:41"
}

// AES RqHeader.Timestamp：Unix 秒數（非毫秒）
// ⚠️ Date.now() 回傳毫秒，必須除以 1000
const timestamp = Math.floor(Date.now() / 1000);
```

## 環境變數

```javascript
// .env（不可提交至版控）
// ECPAY_MERCHANT_ID=3002607
// ECPAY_HASH_KEY=pwFHCqoQZGmho4w6
// ECPAY_HASH_IV=EkRm7iFT261dpevs
// ECPAY_ENV=stage

require('dotenv').config();

const config = {
  merchantId: process.env.ECPAY_MERCHANT_ID,
  hashKey: process.env.ECPAY_HASH_KEY,
  hashIv: process.env.ECPAY_HASH_IV,
  baseUrl: process.env.ECPAY_ENV === 'stage'
    ? 'https://payment-stage.ecpay.com.tw'
    : 'https://payment.ecpay.com.tw',
};
```

## 日誌與監控

```javascript
// 推薦：pino（高效能結構化日誌）
// npm install pino
const pino = require('pino');
const logger = pino({ name: 'ecpay' });

// ⚠️ 絕不記錄 HashKey / HashIV / CheckMacValue
// ✅ 記錄：API 呼叫結果、交易編號、錯誤訊息
logger.info({ merchantTradeNo }, 'ECPay API 呼叫成功');
logger.error({ transCode, rtnCode }, 'ECPay API 錯誤');
```

> **日誌安全規則**：HashKey、HashIV、CheckMacValue 為機敏資料，嚴禁出現在任何日誌、錯誤回報或前端回應中。

## URL Encode 注意

```javascript
// ⚠️ Node.js 的 encodeURIComponent() 空格編碼為 %20 而非 +
// 且不會編碼 ~ 字元和 ' 字元
// ECPay CheckMacValue 要求：%20 → +、~ → %7e、' → %27
// guides/13 的 ecpayUrlEncode 已處理這些轉換
// 請直接使用 guides/13 提供的函式，勿自行實作
```

## 單元測試模式

```javascript
// ecpay.test.js — Jest / Vitest
const { generateCheckMacValue, aesEncrypt, aesDecrypt } = require('./ecpay-crypto');

describe('CheckMacValue', () => {
  test('SHA256 test vector', () => {
    const params = {
      MerchantID: '3002607',
      MerchantTradeNo: 'Test1234567890',
      MerchantTradeDate: '2025/01/01 12:00:00',
      PaymentType: 'aio',
      TotalAmount: '100',
      TradeDesc: '測試',
      ItemName: '測試商品',
      ReturnURL: 'https://example.com/notify',
      ChoosePayment: 'ALL',
      EncryptType: '1',
    };
    expect(generateCheckMacValue(params, 'pwFHCqoQZGmho4w6', 'EkRm7iFT261dpevs'))
      .toBe('291CBA324D31FB5A4BBBFDF2CFE5D32598524753AFD4959C3BF590C5B2F57FB2');
  });
});

describe('AES', () => {
  test('encrypt/decrypt roundtrip', () => {
    const data = { MerchantID: '2000132', BarCode: '/1234567' };
    const encrypted = aesEncrypt(data, 'ejCk326UnaZWKisg', 'q9jcZX8Ib9LM8wYk');
    const decrypted = aesDecrypt(encrypted, 'ejCk326UnaZWKisg', 'q9jcZX8Ib9LM8wYk');
    expect(decrypted.MerchantID).toBe('2000132');
  });
});
```

## Linter / Formatter

```bash
npm install -D eslint prettier
# 推薦 ESLint flat config（eslint.config.js）
# 設定：semi: true, singleQuote: true, trailingComma: 'all'
```
