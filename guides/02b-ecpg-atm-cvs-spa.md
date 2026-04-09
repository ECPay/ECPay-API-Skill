> 對應 ECPay API 版本 | 基於 PHP SDK ecpay/sdk | 最後更新：2026-03

> ⚠️ **SNAPSHOT 2026-03** | 對應 [guides/02 主指南](./02-payment-ecpg.md)

> 📖 本文件為 [guides/02 站內付 2.0 完整指南](./02-payment-ecpg.md) 的子指南 — ATM/CVS + SPA 整合

## ATM / CVS 首次串接快速路徑

> **如果你整合的是 ATM 虛擬帳號或超商代碼付款（CVS），請從這裡開始。**  
> ATM/CVS 的主要差異在於：**不需要 JS SDK**、**CreatePayment 回應包含付款指示**、**ReturnURL 是非同步的**。

### ATM / CVS vs 信用卡流程對比

| 步驟 | 信用卡 | ATM / CVS |
|------|--------|-----------|
| 步驟 0（環境預檢） | 必做 | 必做 |
| 步驟 1（GetToken） | `ChoosePaymentList: '1'`（信用卡） | `ChoosePaymentList: '3'`（ATM）或 `'4'`（CVS） |
| 步驟 2（JS SDK） | **需要** — 渲染信用卡表單 | **不需要** — 跳過此步 |
| 步驟 3（getPayToken） | JS SDK callback 取得 | **不需要** — 跳過此步 |
| 步驟 4（CreatePayment） | `Token` = PayToken | `Token` = GetToken 回傳的 Token（直接用，不需 JS SDK 轉換） |
| 步驟 4 的回應 | `ThreeDURL`（導向 3D 驗證） | `BankCode + vAccount`（ATM）或 `PaymentNo`（CVS）— **顯示給消費者** |
| 步驟 5（ReturnURL 時機） | 3D 驗證完成後立即 | 消費者**實際到 ATM/超商繳費後**才觸發（可能數分鐘到數天後） |

### ATM 付款：GetToken 參數差異

```python
# ATM 付款的 GetToken（與信用卡相比，僅 ChoosePaymentList 不同）
data = post_to_ecpay(f'{ECPG_URL}/Merchant/GetTokenbyTrade', {
    'MerchantID':        MERCHANT_ID,
    'RememberCard':      0,
    'PaymentUIType':     2,
    'ChoosePaymentList': '3',   # 3=ATM
    'OrderInfo': {
        'MerchantTradeDate': time.strftime('%Y/%m/%d %H:%M:%S'),
        'MerchantTradeNo':   trade_no,
        'TotalAmount':       100,
        'ReturnURL':         RETURN_URL,
        'TradeDesc':         '測試商品',
        'ItemName':          '測試商品x1',
    },
    'ATMInfo': {'ExpireDate': 3},   # 允許繳費天數（1~60，預設3）
    'ConsumerInfo': {               # ⚠️ 必填：即使 RememberCard=0，ConsumerInfo 仍需傳入
        'Email': 'test@example.com',  # ← Email 或 Phone 擇一必填
        'Phone': '0912345678',
        'Name':  '測試',
        'CountryCode': '158',
    },
})
token = data['Token']  # 直接取 Token，無需 JS SDK 轉換
```

### ATM 付款：CreatePayment 參數（直接用 Token）

```python
# ATM CreatePayment：PayToken 直接填 GetToken 回傳的 Token 值（跳過 JS SDK 步驟）
data = post_to_ecpay(f'{ECPG_URL}/Merchant/CreatePayment', {
    'MerchantID':      MERCHANT_ID,
    'MerchantTradeNo': trade_no,
    'PayToken':        token,           # ← 直接用 GetToken 回傳的 Token
})
# 成功回應（RtnCode=1）包含（⚠️ 官方規格為巢狀結構）：
# data['OrderInfo']['TradeNo']     ← 綠界交易編號
# data['ATMInfo']['BankCode']      = '812'        ← 銀行代碼
# data['ATMInfo']['vAccount']      = '9103522850' ← 虛擬帳號
# data['ATMInfo']['ExpireDate']    = '2026/03/20' ← 繳費期限
```

### 顯示付款指示給消費者（ATM）

```python
@app.route('/payment/atm', methods=['POST'])
def atm_payment():
    trade_no = 'ATM' + str(int(time.time()))
    token_data = post_to_ecpay(f'{ECPG_URL}/Merchant/GetTokenbyTrade', {
        'MerchantID': MERCHANT_ID, 'RememberCard': 0,
        'PaymentUIType': 2, 'ChoosePaymentList': '3',  # 3=ATM
        'OrderInfo': {
            'MerchantTradeDate': time.strftime('%Y/%m/%d %H:%M:%S'),
            'MerchantTradeNo': trade_no, 'TotalAmount': 100,
            'ReturnURL': RETURN_URL, 'TradeDesc': '測試商品', 'ItemName': '測試商品x1',
        },
        'ATMInfo': {'ExpireDate': 3},
        'ConsumerInfo': {               # ⚠️ 必填：ConsumerInfo 不可省略
            'Email': 'test@example.com',  # ← Email 或 Phone 擇一必填
            'Phone': '0912345678',
            'Name':  '測試',
            'CountryCode': '158',
        },
    })
    pay_data = post_to_ecpay(f'{ECPG_URL}/Merchant/CreatePayment', {
        'MerchantID': MERCHANT_ID,
        'MerchantTradeNo': trade_no, 'PayToken': token_data['Token'],
    })
    # ⚠️ ReturnURL 不會立即觸發，消費者到 ATM 繳費後才觸發
    return render_template_string('''
        <h2>請在期限前完成 ATM 轉帳</h2>
        <p>銀行代碼：<b>{{ bank_code }}</b></p>
        <p>虛擬帳號：<b>{{ vaccount }}</b></p>
        <p>繳費金額：<b>NT${{ amount }}</b></p>
        <p>繳費期限：{{ expire }}</p>
        <p>訂單號碼：{{ trade_no }}</p>
        <p><small>繳費完成後頁面將自動更新（需刷新）</small></p>
    ''', bank_code=pay_data['ATMInfo']['BankCode'], vaccount=pay_data['ATMInfo']['vAccount'],
         amount='100', expire=pay_data['ATMInfo']['ExpireDate'], trade_no=trade_no)
```

### CVS 超商代碼：GetToken 和 CreatePayment 差異

```python
# CVS GetToken（與 ATM 的差異：ChoosePaymentList 和 Info 欄位不同）
'ChoosePaymentList': '4',         # 4=CVS 超商代碼（ATM 為 '3'）
'CVSInfo': {'StoreExpireDate': 10080},  # 逾期分鐘數（7天=10080分鐘）→ 替換 ATMInfo

# CVS CreatePayment（請求格式與 ATM 完全相同：MerchantID + MerchantTradeNo + PayToken）
# 回應包含（⚠️ 官方規格為巢狀結構）：
# data['RtnCode']                  = 1              ← 取號成功（非付款成功）
# data['CVSInfo']['PaymentNo']     = 'LLL22251222'  ← 超商繳費代碼
# data['CVSInfo']['ExpireDate']    = '2026/03/20'   ← 繳費期限
```

### ATM / CVS ReturnURL：非同步接收

```python
@app.route('/ecpay/callback', methods=['POST'])
def callback():
    body = request.get_json(force=True)
    # ⚠️ AES-JSON 雙層驗證：先查 TransCode（傳輸層），再解密 Data（業務層）
    if not body or int(body.get('TransCode', 0)) != 1:
        return '1|OK', 200, {'Content-Type': 'text/plain'}
    data = aes_decrypt(body['Data'])
    rtn_code = int(data.get('RtnCode', 0))

    # ATM/CVS 的 ReturnURL 在消費者「繳款後」才觸發，RtnCode=1 代表實際付款成功
    if rtn_code == 1:
        # ⚠️ 官方規格為巢狀結構：訂單資訊在 OrderInfo 物件內
        order_info = data.get('OrderInfo', {})
        trade_no = order_info.get('MerchantTradeNo', '')
        payment_type = order_info.get('PaymentType', '')   # 'ATM', 'CVS' 等
        print(f'[ReturnURL] ✅ 已繳款 訂單={trade_no} 方式={payment_type}')
        # TODO: 更新訂單狀態為「已付款」，通知消費者繳費成功
    else:
        print(f'[ReturnURL] ❌ 繳款失敗 RtnCode={rtn_code}')
    return '1|OK', 200, {'Content-Type': 'text/plain'}
```

> ⚠️ **測試提醒**：測試環境的 ATM/CVS 付款，可在**綠界測試後台手動觸發 ReturnURL**，不需要真正到 ATM/超商繳費。  
> 路徑：登入 `https://vendor-stage.ecpay.com.tw` → 訂單管理 → 找到你的訂單 → 「模擬付款」。

---

## 非信用卡付款（ATM / CVS / Barcode）的 Callback 時序

> ⚠️ **若你整合的是 ATM、超商代碼（CVS）或超商條碼（Barcode），請務必讀完本節**。  
> ReturnURL **不會在 CreatePayment 之後立即觸發**，這是正常行為，不是 Bug。

### 信用卡 vs ATM/CVS 流程比較

| 流程階段 | 信用卡 | ATM / CVS / Barcode |
|----------|--------|---------------------|
| GetToken | 同 | 同 |
| JS SDK 取 PayToken | **需要**（消費者填卡 → JS callback → PayToken） | **不需要**（直接用 GetToken 回傳的 Token 作為 PayToken）|
| CreatePayment 回應 | Data 含 `ThreeDURL`，導引消費者做 3D 驗證 | Data 含**付款指示**（銀行代碼+虛擬帳號 或 超商代碼），**無** ThreeDURL |
| ReturnURL 觸發時機 | 3D 驗證完成後**立即**（通常數秒內） | 消費者**實際到 ATM / 超商完成繳款後**才送達，可能是數分鐘到數天後 |
| 你的頁面要做什麼 | 接收到 ThreeDURL 後導引消費者去驗證 | **解析 Data，顯示付款指示給消費者** |

### ATM 取號後的 CreatePayment 回應

ATM 付款的 CreatePayment 呼叫成功後，`Data` 解密後會包含：

```json
{
  "RtnCode": 1,
  "RtnMsg": "取號成功",
  "ATMInfo": {
    "BankCode": "812",
    "vAccount": "9103522850",
    "ExpireDate": "2026/12/31"
  },
  "OrderInfo": {
    "MerchantTradeNo": "TEST20260101000001",
    "TradeNo": "2403051234567",
    "TradeAmt": 100,
    "PaymentType": "ATM_TAISHIN"
  }
}
```

> 📌 精確欄位名稱與說明請透過 `web_fetch` 讀取 `references/Payment/站內付2.0API技術文件Web.md` 中「ATM」區段的官方 URL。

**你的後端必須做的事：**

1. AES 解密 `Data` → 從 `ATMInfo` 取出 `BankCode`、`vAccount`、`ExpireDate`
2. 將這三個值存入資料庫（綁定訂單）
3. **回傳頁面給消費者，顯示虛擬帳號與繳費期限**
4. 等待 ReturnURL 的非同步通知

### CVS 超商代碼取號後的 CreatePayment 回應

CVS 付款成功後，`Data` 解密後包含：

```json
{
  "RtnCode": 1,
  "RtnMsg": "取號成功",
  "CVSInfo": {
    "PaymentNo": "12345678901",
    "ExpireDate": "2026/12/31 23:59:59"
  },
  "OrderInfo": {
    "MerchantTradeNo": "TEST20260101000002",
    "TradeNo": "2403051234568",
    "TradeAmt": 100,
    "PaymentType": "CVS_CVS"
  }
}
```

Barcode 則會包含 `Barcode1`、`Barcode2`、`Barcode3`（三段條碼）。

### 非同步 ReturnURL 的處理

- ReturnURL 觸發時機：消費者在 ATM 轉帳或去超商繳費之後，綠界系統確認收款後才發送
- 格式：**JSON POST**（與信用卡 ReturnURL 相同，需 AES 解密 `Data`）
- 解密後 `Data.RtnCode === 1` 代表付款成功

```php
// ReturnURL handler — ATM/CVS 和信用卡的格式完全相同
$data = $sdk->decryptData($request->Data);  // AES 解密
if ($data['RtnCode'] === 1) {  // ECPG Data 為 JSON 解密 → 整數，用 === 1
    // 訂單標記為已付款
}
echo '1|OK';  // 必須回傳此字串
```

### 若消費者遺失付款資訊

可用 `QueryPaymentInfo` 重新查詢（呼叫 `ecpayment` 網域的 Cashier/QueryPaymentInfo，非 `ecpg` 網域）：

```php
// 原始範例：scripts/SDK_PHP/example/Payment/Ecpg/QueryPaymentInfo.php
$input = [
    'MerchantID' => '3002607',
    'RqHeader'   => ['Timestamp' => time()],
    'Data'       => [
        'PlatformID'      => '',         // 一般商店填空字串；平台商模式填平台商 ID
        'MerchantID'      => '3002607',
        'MerchantTradeNo' => '你的訂單號',
    ],
];
$response = $postService->post($input, 'https://ecpayment-stage.ecpay.com.tw/1.0.0/Cashier/QueryPaymentInfo');
// $response['Data'] 解密後包含 BankCode / vAccount 或 PaymentNo
```

> 📌 完整參數請參考 `references/Payment/站內付2.0API技術文件Web.md` → 查詢取號結果 URL。

### 測試注意事項

- 測試環境的 ATM/CVS 付款，綠界提供**模擬付款功能**，可在測試後台手動觸發 ReturnURL，不需要真正去 ATM/超商繳費。
- 測試時 ReturnURL 必須可公開存取（同信用卡流程）。若本機開發，使用 `ngrok` 或 `Cloudflare Tunnel` 建立臨時公開 URL。

---

## 🖥️ SPA / React / Vue / Next.js 整合架構

> **前後端分離框架常見陷阱**：ThreeDURL 必須用 `window.location.href` 導向，不可用前端 router（`router.push` / `<Link>` / `navigate()`），否則 3D 驗證頁面會被前端路由攔截而失效。

### 整體架構設計

```
                     ┌─────────────────────────────────────────────┐
你的前端（React/Vue）  │  你的後端 API                               │
                      │                                             │
[頁面載入]            │                                             │
  │ POST /api/ecpay/gettoken                                        │
  │──────────────────►│── GetTokenbyTrade ──►  ecpg-stage.ecpay    │
  │◄──────────────────│       { token }                             │
  │                   │                                             │
  │ ECPay.createPayment(token)  [JS SDK 直接與 ECPay 通訊]          │
  │ 消費者填卡 → getPayToken callback → PayToken                    │
  │                   │                                             │
  │ POST /api/ecpay/create_payment (payToken, merchantTradeNo)      │
  │──────────────────►│── CreatePayment ────►  ecpg-stage.ecpay    │
  │◄──────────────────│  { threeDUrl: "..." }                       │
  │                   │                                             │
  │ if (threeDUrl) window.location.href = threeDUrl  ← ⚠️ 必須這樣 │
  │ ← 不可用 router.push() 或 navigate() ←────────────────────     │
  │                   │                                             │
  │   [3D 驗證完成後瀏覽器被導向 OrderResultURL]                    │
  │                                                ReturnURL → │   │
  │                                             後端接收 1|OK   │   │
                     └─────────────────────────────────────────────┘
```

**關鍵規則**：
- ECPay JS SDK 腳本必須從**後端取得 Token 後**才呼叫 `createPayment`，Token 是每筆交易的一次性憑證
- **禁止前端直接呼叫 ecpg 端點**：API Key 必須在後端，前端只呼叫你自己的後端 API
- `ThreeDURL` 是 ECPay 的外部頁面，`window.location.href` 是唯一正確的導向方式

### React 完整範例（Hooks）

```jsx
// components/PaymentForm.jsx
import { useEffect, useState, useRef } from 'react';

// ⚠️ SDK 依賴 jQuery + node-forge，必須先載入（見下方 loadDependencies）
// ⚠️ JS SDK 一律從正式 domain 載入，透過 initialize('Stage') 切換環境
const ECPAY_SDK_URL = 'https://ecpg.ecpay.com.tw/Scripts/sdk-1.0.0.js';

export default function PaymentForm({ amount = 100, onSuccess, onError }) {
  const [status, setStatus] = useState('初始化中…');
  const [token, setToken] = useState(null);
  const containerRef = useRef(null);
  // 每筆交易產生唯一 MerchantTradeNo，儲存在 ref 避免 closure 捕捉舊值
  const tradeNoRef = useRef('Test' + Date.now());

  // 步驟 1：載入 JS SDK 腳本（避免重複載入）
  useEffect(() => {
    if (document.getElementById('ecpay-sdk')) return;
    const script = document.createElement('script');
    script.id = 'ecpay-sdk';
    script.src = ECPAY_SDK_URL;
    script.onload = () => fetchToken();
    document.head.appendChild(script);
    return () => { /* 不移除腳本，避免後續訂單重新載入 */ };
  }, []);

  // 步驟 1b：向後端取 Token
  async function fetchToken() {
    setStatus('取得 Token 中…');
    try {
      const res = await fetch('/api/ecpay/gettoken', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ merchantTradeNo: tradeNoRef.current, amount }),
      });
      const { token, error } = await res.json();
      if (error) throw new Error(error);
      setToken(token);
    } catch (err) {
      setStatus('GetToken 失敗：' + err.message);
      onError?.(err);
    }
  }

  // 步驟 2：Token 就緒後初始化 SDK 並渲染付款表單
  // ⚠️ createPayment() 必須在 initialize() callback 內（官方 WebJS.html 寫法）
  //    寫在外面會造成競態條件：SDK 未初始化完就嘗試渲染 → 永遠轉圈
  useEffect(() => {
    if (!token || !window.ECPay) return;
    setStatus('初始化 SDK…');
    window.ECPay.initialize('Stage', 1, function(initErr) {
      if (initErr) { setStatus('SDK 初始化失敗：' + initErr); onError?.(new Error(initErr)); return; }
      setStatus('渲染付款表單…');
      // ⚠️ createPayment 使用 positional 參數：(token, language, callback, version)
      //    頁面必須有 <div id="ECPayPayment"></div>（固定 ID，不可自訂）
      window.ECPay.createPayment(token, 'zh-TW', function(errMsg) {
        if (errMsg != null) {
          setStatus('建立付款 UI 失敗：' + errMsg);
          onError?.(new Error(errMsg));
        }
      }, 'V2');
    });
  }, [token]);

  // 步驟 3：取得 PayToken（消費者填完付款資訊後觸發）
  const handleGetPayToken = useCallback(() => {
    if (!window.ECPay) return;
    // ⚠️ getPayToken callback 為 (paymentInfo, errMsg) 雙參數
    window.ECPay.getPayToken(async function(paymentInfo, errMsg) {
      if (errMsg != null) {
        setStatus('PayToken 失敗：' + errMsg);
        onError?.(new Error(errMsg));
        return;
      }
      setStatus('送出付款中…');
      try {
        // 步驟 4：後端 CreatePayment
        const res = await fetch('/api/ecpay/create_payment', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            payToken: paymentInfo.PayToken,
            merchantTradeNo: tradeNoRef.current,
          }),
        });
        const { threeDUrl, error } = await res.json();
        if (error) throw new Error(error);

        if (threeDUrl) {
          // 步驟 5：⚠️ 必須用 window.location.href，不可用 router.push
          setStatus('導向 3D 驗證頁面…');
          window.location.href = threeDUrl;
        } else {
          setStatus('✅ 付款成功！');
          onSuccess?.({ merchantTradeNo: tradeNoRef.current });
        }
      } catch (err) {
        setStatus('CreatePayment 失敗：' + err.message);
        onError?.(err);
      }
    });
  }, []);

  return (
    <div>
      <p style={{ color: '#666' }}>{status}</p>
      <div id="ECPayPayment" ref={containerRef} />
    </div>
  );
}
```

### Next.js API Routes（後端）

```typescript
// app/api/ecpay/gettoken/route.ts
import { NextRequest, NextResponse } from 'next/server';
import { aesEncrypt, aesDecrypt, MERCHANT_ID } from '@/lib/ecpay';

export async function POST(req: NextRequest) {
  const { merchantTradeNo, amount } = await req.json();
  const now = new Date();
  const tradeDate = now.toLocaleString('zh-TW', {
    year: 'numeric', month: '2-digit', day: '2-digit',
    hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false,
  }).replace(/\//g, '/');

  const payload = {
    MerchantID: MERCHANT_ID, RememberCard: 1,
    PaymentUIType: 2, ChoosePaymentList: '1',
    OrderInfo: {
      MerchantTradeDate: tradeDate, MerchantTradeNo: merchantTradeNo,
      TotalAmount: amount, ReturnURL: `${process.env.BASE_URL}/api/ecpay/callback`,
      TradeDesc: '商品付款', ItemName: '商品x1',
    },
    CardInfo: { OrderResultURL: `${process.env.BASE_URL}/payment/result` },  // Redeem 選填，省略避免格式問題
    ConsumerInfo: {             // ⚠️ 必填：整個 Object 不可省略
      MerchantMemberID: 'member001',  // ← RememberCard=1 時必填；=0 時可省略
      Email: 'customer@example.com',  // ← Email 或 Phone 擇一必填
      Phone: '0912345678',
      Name: '顧客',
      CountryCode: '158',
    },
  };

  const body = {
    MerchantID: MERCHANT_ID,
    RqHeader: { Timestamp: Math.floor(Date.now() / 1000) },
    Data: aesEncrypt(payload),
  };
  const ecRes = await fetch('https://ecpg-stage.ecpay.com.tw/Merchant/GetTokenbyTrade', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  });
  const json = await ecRes.json();
  if (json.TransCode !== 1) return NextResponse.json({ error: json.TransMsg }, { status: 500 });
  const data = aesDecrypt(json.Data);
  return NextResponse.json({ token: data.Token });
}
```

```typescript
// app/api/ecpay/callback/route.ts  (ReturnURL — S2S JSON POST)
import { NextRequest, NextResponse } from 'next/server';
import { aesDecrypt } from '@/lib/ecpay';
import { db } from '@/lib/db';

export async function POST(req: NextRequest) {
  const body = await req.json();
  // ⚠️ AES-JSON 雙層驗證：先查 TransCode（傳輸層），再解密 Data（業務層）
  if (body.TransCode !== 1) {
    return new NextResponse('1|OK', { headers: { 'Content-Type': 'text/plain' } });
  }
  const data = aesDecrypt(body.Data);
  if (Number(data.RtnCode) === 1) {
    await db.order.update({
      where: { tradeNo: data.OrderInfo?.MerchantTradeNo },
      data: { status: 'paid', paidAt: new Date() },
    });
  }
  // ⚠️ 回應純文字 '1|OK'（不可是 JSON）
  return new NextResponse('1|OK', { headers: { 'Content-Type': 'text/plain' } });
}
```

```typescript
// app/payment/result/page.tsx  (OrderResultURL — 消費者瀏覽器 Form POST)
// Next.js App Router 不原生支援 Form POST 接收，建議用 Pages Router 或 Route Handler：
// app/api/ecpay/result/route.ts
export async function POST(req: NextRequest) {
  const formData = await req.formData();
  const resultDataStr = formData.get('ResultData') as string;
  // ⚠️ ResultData 是 JSON 字串，需先 JSON.parse 取外層結構，再 AES 解密 Data
  const outer = JSON.parse(resultDataStr);   // ← Step 1：JSON 解析外層 {TransCode, Data}
  if (outer.TransCode !== 1) {
    return new NextResponse('<h1>資料傳輸錯誤</h1>', { headers: { 'Content-Type': 'text/html' } });
  }
  const data = aesDecrypt(outer.Data);       // ← Step 2：AES 解密 Data 欄位
  // 重導到結果頁（帶查詢參數，讓前端渲染）
  const status = Number(data.RtnCode) === 1 ? 'success' : 'fail';
  const url = `/payment/result?status=${status}&tradeNo=${data.OrderInfo?.MerchantTradeNo}`;
  return NextResponse.redirect(new URL(url, req.url));
}
```

> ⚠️ **Next.js App Router 的 ThreeDURL 處理**：3D 驗證完成後，ECPay 會將消費者導向 `OrderResultURL`。若 `OrderResultURL` 是 Next.js API Route（接收 Form POST），需用 `redirect` 將消費者轉到前端頁面渲染結果，**不可直接在 Route Handler 回傳 HTML**（因 Response Content-Type 需為 HTML 且不受 Next.js Layout 包覆）。

### Vue 3 / Nuxt 3 快速整合

```vue
<!-- components/EcpayPayment.vue -->
<template>
  <div>
    <p class="status">{{ status }}</p>
    <div id="ECPayPayment" />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
const props = defineProps({ amount: { type: Number, default: 100 } });
const emit = defineEmits(['success', 'error']);
const status = ref('初始化中…');
const tradeNo = 'Test' + Date.now();

onMounted(async () => {
  // 載入 ECPay SDK
  // ⚠️ 三個依賴必須按順序載入：jQuery → node-forge → ECPay SDK
  await loadScript('https://code.jquery.com/jquery-3.7.1.min.js');
  await loadScript('https://cdn.jsdelivr.net/npm/node-forge@0.7.0/dist/forge.min.js');
  // ⚠️ JS SDK 一律從正式 domain 載入，透過 initialize('Stage') 切換環境
  await loadScript('https://ecpg.ecpay.com.tw/Scripts/sdk-1.0.0.js');
  status.value = '取得 Token 中…';
  const res = await fetch('/api/ecpay/gettoken', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ merchantTradeNo: tradeNo, amount: props.amount }),
  });
  const { token, error } = await res.json();
  if (error) { status.value = '失敗：' + error; return; }

  // ⚠️ createPayment() 必須在 initialize() callback 內（官方 WebJS.html 寫法）
  //    寫在外面會造成競態條件：SDK 未初始化完就嘗試渲染 → 永遠轉圈
  window.ECPay.initialize('Stage', 1, function(initErr) {
    if (initErr) { status.value = 'SDK 初始化失敗：' + initErr; return; }
    // ⚠️ createPayment 使用 positional 參數：(token, language, callback, version)
    //    頁面必須有 <div id="ECPayPayment"></div>（固定 ID，不可自訂）
    window.ECPay.createPayment(token, 'zh-TW', function(errMsg) {
      if (errMsg != null) { status.value = '建立付款 UI 失敗：' + errMsg; }
    }, 'V2');
  });
});

// 步驟 3：取得 PayToken（由按鈕或表單觸發）
async function handleGetPayToken() {
  // ⚠️ getPayToken callback 為 (paymentInfo, errMsg) 雙參數
  window.ECPay.getPayToken(async function(paymentInfo, errMsg) {
    if (errMsg != null) { status.value = '失敗：' + errMsg; return; }
    const r = await fetch('/api/ecpay/create_payment', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ payToken: paymentInfo.PayToken, merchantTradeNo: tradeNo }),
    });
    const { threeDUrl, error } = await r.json();
    if (error) { status.value = '付款失敗：' + error; return; }
    if (threeDUrl) {
      window.location.href = threeDUrl;  // ⚠️ 不可用 router.push 或 navigateTo
    } else {
      emit('success', { tradeNo });
    }
  });
}

function loadScript(src) {
  return new Promise((resolve, reject) => {
    if (document.querySelector(`script[src="${src}"]`)) return resolve();
    const s = document.createElement('script');
    s.src = src; s.onload = resolve; s.onerror = reject;
    document.head.appendChild(s);
  });
}
</script>
```

---

## 延伸閱讀

| 子指南 | 內容 |
|--------|------|
| [02a — 首次串接快速路徑](./02a-ecpg-quickstart.md) | GetToken/CreatePayment 最快成功路徑、Python/Node.js 完整範例 |
| **本文（02b）** | ATM/CVS 快速路徑、SPA/React/Vue 整合 |
| [02c — App / 正式環境](./02c-ecpg-app-production.md) | iOS/Android App 整合、Apple Pay、正式環境切換、**TransCode ≠ 1 錯誤降級**(ATM/CVS 流程亦適用) |
| [02 — 完整指南 Hub](./02-payment-ecpg.md) | 綁卡/退款/查詢/對帳/安全 |

> 💡 **ATM/CVS 上線前必讀**:[guides/02c §3. TransCode ≠ 1 錯誤降級](./02c-ecpg-app-production.md#3-transcode1-錯誤降級)提供帶重試的安全呼叫範例。即使本指南是 ATM/CVS 流程,該降級策略(伺服器時鐘偏差、負載高峰超時)同樣適用。

