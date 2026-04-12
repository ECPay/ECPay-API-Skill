# TypeScript — ECPay 整合程式規範

> 本檔為 AI 生成 ECPay 整合程式碼時的 TypeScript 專屬規範。
> 加密函式：[guides/13 §TypeScript](../13-checkmacvalue.md) + [guides/14 §TypeScript](../14-aes-encryption.md)
> E2E 範例：[guides/23 §TypeScript](../23-multi-language-integration.md)

## 版本與環境

- **最低版本**：TypeScript 5.0+、Node.js 18+
- **推薦版本**：TypeScript 5.4+、Node.js 20 LTS+
- **安裝**：`npm install -D typescript @types/node @types/express ts-node`

## 推薦依賴

```json
{
  "dependencies": {
    "express": "^4.18",
    "dotenv": "^16.0"
  },
  "devDependencies": {
    "typescript": "^5.4",
    "@types/node": "^20",
    "@types/express": "^4"
  }
}
```

> **內建 crypto 即可**：Node.js `crypto` 模組已包含 AES-128-CBC 和 SHA256，TypeScript 透過 `@types/node` 取得完整型別，無需第三方加密庫。

## tsconfig 關鍵設定

```json
{
  "compilerOptions": {
    "strict": true,
    "target": "ES2020",
    "module": "commonjs",          // ESM 專案改用 "module": "nodenext"
    "esModuleInterop": true,
    "resolveJsonModule": true,
    "skipLibCheck": true,
    "outDir": "./dist"
  }
}
```

## 命名慣例

與 Node.js 相同：函式 `camelCase`、類別 `PascalCase`、常數 `UPPER_SNAKE_CASE`。
ECPay 參數名保持原始 PascalCase（`MerchantID`、`HashKey`）。

## 型別定義（核心優勢）

```typescript
// ecpay-types.ts — ECPay 共用型別定義

/** AIO 金流送出參數 */
interface AioParams {
  MerchantID: string;
  MerchantTradeNo: string;
  MerchantTradeDate: string;
  PaymentType: 'aio';
  TotalAmount: string;
  TradeDesc: string;
  ItemName: string;
  ReturnURL: string;
  ChoosePayment: string;
  EncryptType: '1';
  CheckMacValue?: string;
  [key: string]: string | undefined;  // 動態額外欄位
}

/** AES-JSON 請求結構 */
interface AesRequest {
  MerchantID: string;
  RqHeader: {
    Timestamp: number;
    // Revision 依服務不同填入對應值（詳見 guides/14 §使用場景 / guides/19 §2.1 AES-JSON）:
    //   發票 B2C: "3.0.0" | 發票 B2B: "1.0.0"（且必填 RqID UUID v4）
    //   全方位物流 / 跨境物流: "1.0.0"
    //   站內付 2.0 / 幕後授權 / 幕後取號 / 電子票證 / 直播收款: 不使用（省略此欄位）
    // ⚠️ 把電子發票的 "3.0.0" 加到站內付 2.0 請求會導致 TransCode ≠ 1
    Revision?: string;
    /** 僅 B2B 發票必填，每次請求必須唯一 UUID v4 */
    RqID?: string;
  };
  Data: string;
}

/** AES-JSON 回應結構 */
interface AesResponse {
  TransCode: number;
  TransMsg: string;
  Data: string;
}

/** AIO Callback 參數 */
interface AioCallbackParams {
  MerchantID: string;
  MerchantTradeNo: string;
  RtnCode: string;     // ⚠️ 字串
  RtnMsg: string;
  TradeNo: string;
  TradeAmt: string;
  PaymentDate: string;
  PaymentType: string;
  CheckMacValue: string;
  SimulatePaid: string;
  [key: string]: string;
}

/** 付款方式枚舉 */
type ChoosePayment = 'ALL' | 'Credit' | 'ATM' | 'CVS' | 'BARCODE' | 'WebATM'
  | 'TWQR' | 'BNPL' | 'ApplePay' | 'WeiXin';

/** DoAction 操作類型（僅限信用卡） */
type CreditAction = 'C' | 'R' | 'E' | 'N';
// C=請款, R=退款, E=取消, N=放棄

/** ECPay 環境設定 */
interface EcpayConfig {
  merchantId: string;
  hashKey: string;
  hashIv: string;
  baseUrl: string;
}

export type {
  AioParams, AesRequest, AesResponse, AioCallbackParams,
  ChoosePayment, CreditAction, EcpayConfig,
};
```

## 錯誤處理

```typescript
class EcpayApiError extends Error {
  constructor(
    public readonly transCode: number,
    public readonly rtnCode: string | null,
    message: string,
  ) {
    super(`TransCode=${transCode}, RtnCode=${rtnCode}: ${message}`);
    this.name = 'EcpayApiError';
  }
}

async function callAesApi<T>(
  url: string,
  requestBody: AesRequest,
  hashKey: string,
  hashIv: string,
): Promise<T> {
  const resp = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(requestBody),
    signal: AbortSignal.timeout(30_000),
  });

  if (resp.status === 403) throw new EcpayApiError(-1, null, 'Rate Limited');
  if (!resp.ok) throw new EcpayApiError(-1, null, `HTTP ${resp.status}`);

  const result: AesResponse = await resp.json();

  // 雙層錯誤檢查
  if (result.TransCode !== 1) {
    throw new EcpayApiError(result.TransCode, null, result.TransMsg);
  }
  const data = aesDecrypt(result.Data, hashKey, hashIv) as T & Record<string, unknown>;
  if (String(data.RtnCode) !== '1') {
    throw new EcpayApiError(1, String(data.RtnCode), String(data.RtnMsg ?? ''));
  }
  return data;
}
```

## HTTP Client 設定

```typescript
// Node.js 18+ 內建 fetch，無需額外依賴
// AbortSignal.timeout() 比手動 AbortController 更簡潔
const resp = await fetch(url, {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify(requestBody),
  signal: AbortSignal.timeout(30_000),
});

// 連線池管理：undici（Node.js 底層 HTTP 引擎）
// import { Agent } from 'undici';
// const agent = new Agent({ keepAliveTimeout: 30_000, connections: 10 });

// Retry 策略（僅限 transient errors）
// ⚠️ 403 (Rate Limit) 不可自動重試 — 需等待約 30 分鐘
// 建議以指數退避重試 500/502/503，最多 3 次
```

## Callback Handler 模板

```typescript
import express, { Request, Response } from 'express';
import crypto from 'crypto';

const app = express();
app.use(express.urlencoded({ extended: false }));

app.post('/ecpay/callback', (req: Request, res: Response) => {
  const params = req.body as AioCallbackParams;

  // 1. Timing-safe CMV 驗證
  const receivedCmv = params.CheckMacValue ?? '';
  const { CheckMacValue: _, ...paramsWithoutCmv } = params;
  const expectedCmv = generateCheckMacValue(paramsWithoutCmv, HASH_KEY, HASH_IV);

  // ⚠️ timingSafeEqual 在長度不同時會 throw — 必須先檢查長度
  const receivedBuf = Buffer.from(receivedCmv);
  const expectedBuf = Buffer.from(expectedCmv);
  if (receivedBuf.length !== expectedBuf.length ||
      !crypto.timingSafeEqual(receivedBuf, expectedBuf)) {
    return res.status(400).send('CheckMacValue Error');
  }

  // 2. RtnCode 是字串
  if (params.RtnCode === '1') {
    // 處理成功
  }

  // 3. HTTP 200 + "1|OK"
  res.status(200).type('text/plain').send('1|OK');
});
```

## 日誌與監控

```typescript
// 推薦：pino（高效能結構化日誌，完整 TypeScript 支援）
// npm install pino @types/pino
import pino from 'pino';
const logger = pino({ name: 'ecpay' });

// ⚠️ 絕不記錄 HashKey / HashIV / CheckMacValue
// ✅ 記錄：API 呼叫結果、交易編號、錯誤訊息
logger.info({ merchantTradeNo }, 'ECPay API 呼叫成功');
logger.error({ transCode, rtnCode }, 'ECPay API 錯誤');
```

> **日誌安全規則**：HashKey、HashIV、CheckMacValue 為機敏資料，嚴禁出現在任何日誌、錯誤回報或前端回應中。

## URL Encode 注意

```typescript
// ⚠️ encodeURIComponent() 空格編碼為 %20 而非 +
// 且不會編碼 ~ 字元和 ' 字元
// ECPay CheckMacValue 要求：%20 → +、~ → %7e、' → %27
// guides/13 的 ecpayUrlEncode 已處理這些轉換
// 請直接使用 guides/13 提供的函式，勿自行實作
```

> ⚠️ ECPay Callback URL 僅支援 port 80 (HTTP) / 443 (HTTPS)，開發環境使用 ngrok 轉發到本機任意 port。

## 日期與時區

```typescript
// ⚠️ ECPay 所有時間欄位皆為台灣時間（UTC+8）

// MerchantTradeDate 格式：yyyy/MM/dd HH:mm:ss（非 ISO 8601）
function getMerchantTradeDate(): string {
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
const timestamp: number = Math.floor(Date.now() / 1000);
```

## 環境變數

```typescript
// .env（不可提交至版控）
// ECPAY_MERCHANT_ID=3002607
// ECPAY_HASH_KEY=pwFHCqoQZGmho4w6
// ECPAY_HASH_IV=EkRm7iFT261dpevs
// ECPAY_ENV=stage

import dotenv from 'dotenv';
dotenv.config();

const config: EcpayConfig = {
  merchantId: process.env.ECPAY_MERCHANT_ID!,
  hashKey: process.env.ECPAY_HASH_KEY!,
  hashIv: process.env.ECPAY_HASH_IV!,
  baseUrl: process.env.ECPAY_ENV === 'stage'
    ? 'https://payment-stage.ecpay.com.tw'
    : 'https://payment.ecpay.com.tw',
};

// ⚠️ 正式環境建議使用 zod 驗證環境變數，避免 undefined 導致執行期錯誤
// import { z } from 'zod';
// const envSchema = z.object({
//   ECPAY_MERCHANT_ID: z.string().min(1),
//   ECPAY_HASH_KEY: z.string().length(16),
//   ECPAY_HASH_IV: z.string().length(16),
//   ECPAY_ENV: z.enum(['stage', 'production']).default('stage'),
// });
// const env = envSchema.parse(process.env);
```

## 單元測試模式

```bash
npm install -D jest ts-jest @types/jest
# jest.config.ts: preset: 'ts-jest'
```

```typescript
import { generateCheckMacValue, aesEncrypt, aesDecrypt } from './ecpay-crypto';

describe('CMV SHA256', () => {
  it('matches test vector', () => {
    const params = { /* ... test vector params ... */ };
    expect(generateCheckMacValue(params, 'pwFHCqoQZGmho4w6', 'EkRm7iFT261dpevs'))
      .toBe('291CBA324D31FB5A4BBBFDF2CFE5D32598524753AFD4959C3BF590C5B2F57FB2');
  });
});
```

## Linter / Formatter

```bash
npm install -D eslint @typescript-eslint/parser @typescript-eslint/eslint-plugin prettier
# 推薦啟用 strict mode + noUncheckedIndexedAccess
```
