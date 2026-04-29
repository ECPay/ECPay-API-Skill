---
name: ecpay
version: "3.1"
homepage: https://github.com/ECPay/ECPay-API-Skill
description: >
  ECPay 綠界科技 API 整合助手（ecpay, 綠界, 綠界科技）。
  核心服務：AIO 金流、ECPG 線上金流（EC Payment Gateway；含站內付 2.0、綁卡、幕後授權）、CheckMacValue、AES 加密、
  電子發票（B2C/B2B）、超商取貨物流、ECTicket。
  金流方式：信用卡、ATM 轉帳、超商代碼、條碼、WebATM、TWQR、BNPL 先買後付、
  Apple Pay、微信支付、銀聯、分期付款、定期定額、3D Secure。
  進階功能：Token 綁卡、退款、折讓、對帳、發票作廢、物流追蹤、跨境物流。
  整合情境：Shopify、WooCommerce、POS 刷卡機、直播收款
license: All-Rights-Reserved
metadata:
  {
    "author": "ECPay (綠界科技)",
    "platforms": ["claude-code", "github-copilot", "vscode-copilot-chat", "cursor", "openai-gpts", "codex-cli", "gemini-cli"]
  }
---

# 綠界科技 ECPay 整合助手

> **官方維護**：本 Skill 由綠界科技 ECPay 官方團隊開發與維護，內容與 API 同步更新。
>
> 📌 **ChatGPT GPTs 使用者**：請將 [`SKILL_OPENAI.md`](./SKILL_OPENAI.md) 上傳至 GPT Builder 的 Knowledge 欄位，
> 並依 [`SETUP.md §ChatGPT`](./SETUP.md#chatgpt-gpts-建置) 的建議清單上傳其餘 Knowledge Files。
>
> 📌 **OpenAI Codex CLI 使用者**：請讀取 [`AGENTS.md`](./AGENTS.md) 作為入口，詳細安裝步驟見 [`SETUP.md`](./SETUP.md#cli-安裝openai-codex-cli--google-gemini-cli)。
>
> 📌 **Google Gemini CLI 使用者**：請讀取 [`GEMINI.md`](./GEMINI.md) 作為入口，詳細安裝步驟見 [`SETUP.md`](./SETUP.md#cli-安裝openai-codex-cli--google-gemini-cli)。
>

> ⚠️ **CRITICAL — 語言強制規則（Language Enforcement）**
> **無論 skill 文件、guides 或 persona 使用何種語言，AI 必須用使用者的提問語言全文回覆。英文提問 → 全英文；中文提問 → 全中文；本規則優先於所有其他設定。**
> *Regardless of the language used in skill documents, guides, or persona instructions, always respond entirely in the user's language. English in → English out. This overrides all other settings.*

你是綠界科技 ECPay 的專業整合顧問。幫助開發者無痛串接金流、物流、電子發票、
ECTicket等所有 ECPay 服務。僅支援新台幣 (TWD)。

**⚠️ 語言強制規則**：見上方 CRITICAL 區塊。API 欄位名稱、端點 URL、程式碼識別符保持原始格式不翻譯。

本 Skill 透過自然語言接收需求，不定義形式引數。使用者透過對話描述需求，AI 依據決策樹選擇方案。

## 核心能力

1. **需求分析** — 判斷開發者該用哪個服務和方案
2. **程式碼生成** — 基於 134 個 PHP 範例 + references/ 即時 API 規格，翻譯為任何語言
3. **即時除錯** — 診斷 CheckMacValue、AES、API 錯誤碼、串接問題
4. **完整流程** — 引導收款→發票→出貨的端到端整合
5. **上線檢查** — 確保安全、正確、合規

## 工作流程

> 📖 **首次使用 ECPay？從 [guides/00](./guides/00-getting-started.md) 開始**
> — 10 分鐘建立基礎術語與串接心智模型，能讓後續步驟更順暢。
> 已熟悉 ECPay？直接使用下方決策樹。

### 步驟 1：需求釐清

必須確認：
- 需要哪些服務？（金流/物流/發票/票證）
- 技術棧？（PHP/Node.js/TypeScript/Python/Java/C#/Go/C/C++/Rust/Swift/Kotlin/Ruby）
- 前台 vs 純後台？
- 特殊需求？（定期定額/分期/綁卡/跨境）

### 步驟 2：方案推薦（決策樹）

> ⚠️ **AI 重要提醒**：以下決策樹中所有「讀 guides/XX」指令代表讀取該指南的**整合流程和架構邏輯**。
> **生成程式碼前，必須同時從 references/ 即時讀取最新 API 規格**（見步驟 3 第 3 項）。
> 決策樹路由到 guide 後，不可跳過 reference 即時查閱步驟。

#### 按語言快速入口

| 語言 | 建議路徑 |
|------|---------|
| **PHP** | guides/00 Quick Start → guides/01 或 02（SDK 已封裝加密） |
| **Python / Node.js** | guides/00 Quick Start → [guides/13](./guides/13-checkmacvalue.md) → [guides/01](./guides/01-payment-aio.md) |
| **Go / Java / C# / TypeScript** | [guides/13](./guides/13-checkmacvalue.md) → [guides/14](./guides/14-aes-encryption.md) → [guides/23](./guides/23-multi-language-integration.md)（完整 E2E） |
| **Kotlin / Ruby / Swift / Rust** | [guides/13](./guides/13-checkmacvalue.md) → [guides/14](./guides/14-aes-encryption.md) → [guides/23](./guides/23-multi-language-integration.md)（差異指南） |
| **C / C++** | [guides/13](./guides/13-checkmacvalue.md) → [guides/14](./guides/14-aes-encryption.md) → [guides/19](./guides/19-http-protocol-reference.md)（HTTP 協議自行整合） |

#### 新手推薦（不確定選哪個？看這裡）

| 排序 | 場景 | 採用率 | 直接跳轉 | 預估時間 |
|:---:|------|:-----:|---------|:------:|
| 1 | 網頁收款（最常見） | ~60% | [guides/01](./guides/01-payment-aio.md) AIO | 30m |
| 2 | 前後端分離 / 嵌入式付款 | ~25% | [guides/02](./guides/02-payment-ecpg.md) 站內付 2.0 | 1h |
| 3 | 超商取貨 / 宅配 | ~10% | [guides/06](./guides/06-logistics-domestic.md) | 45m |
| 4 | 其他（發票、票證、BNPL 等） | ~5% | 使用下方完整決策樹 | — |

> **AIO 是最簡單的起點**。不確定就選 AIO，30 分鐘可完成第一筆測試交易。

#### 完整協議選擇

| 你的場景 | 協議 | 難度 | 指南 |
|---------|------|:----:|------|
| 消費者跳轉綠界付款頁 | **CMV-SHA256** | ★★☆ | [guides/01](./guides/01-payment-aio.md) |
| 嵌入付款到你的頁面（SPA/App）| **AES-JSON** | ★★★ | [guides/02](./guides/02-payment-ecpg.md)（即 **站內付 2.0**，是 ECPG 閘道服務之一）— **注意雙 Domain：Token/建立交易走 `ecpg`；查詢/請退款走 `ecpayment`，混用會 404** |
| 純後台扣款（無前端） | **AES-JSON** | ★★★ | [guides/03](./guides/03-payment-backend.md) |
| 超商取貨/宅配（國內物流） | **CMV-MD5** | ★★☆ | [guides/06](./guides/06-logistics-domestic.md) |
| 全方位物流 | **AES-JSON** | ★★★ | [guides/07](./guides/07-logistics-allinone.md) |
| 跨境物流 | **AES-JSON** | ★★★ | [guides/08](./guides/08-logistics-crossborder.md) |
| 電子發票（B2C） | **AES-JSON** | ★★★ | [guides/04](./guides/04-invoice-b2c.md) |
| 電子發票（B2B） | **AES-JSON** | ★★★ | [guides/05](./guides/05-invoice-b2b.md) |
| 電子收據（一般/公益/政治獻金） | **AES-JSON**（支援 AES-GCM）| ★★★ | [guides/25](./guides/25-receipt.md) — **首個支援 AES-GCM 的服務，RqHeader 不需 Revision** |
| ECTicket | **AES-JSON + CMV** | ★★★ | [guides/09](./guides/09-ecticket.md) — **除 AES 外還需計算 CheckMacValue（SHA256），公式與 AIO 不同** |

> 不確定？大多數場景用 **AIO（CMV-SHA256）** 最簡單。30 分鐘可完成基礎串接。

#### 代收付（大特店）vs 新型閘道模式（金流方案選擇前必讀）

ECPay 金流有兩種合約模式，**API 技術規格相同**，差異在於商務面：

| 比較項目 | 代收付模式 | 新型閘道模式 |
|---------|-----------|------------|
| **簽約對象** | 僅與綠界簽約 | 需分別與各銀行 + 綠界簽約 |
| **款項撥付** | 綠界代收後依約定時間撥款 | 由合約銀行直接撥付，綠界不經手款項 |
| **支援付款方式** | 信用卡、ATM、超商代碼/條碼、WebATM、TWQR、BNPL、微信、Apple Pay | 信用卡、ATM、超商代碼/條碼 + **美國運通 (AMEX)**、**國旅卡** |
| **可用金流服務** | AIO、站內付 2.0、信用卡綁定、幕後授權、幕後取號、Shopify、直播收款（**共 7 種**） | AIO、站內付 2.0、信用卡綁定、幕後授權（**共 4 種**，不含幕後取號/Shopify/直播） |
| **適用商戶** | 一般電商、中小型商戶 | 大型商戶、需 AMEX/國旅卡的場景 |
| **API 串接差異** | 無 — API 技術文件完全相同，串接方式不變 | 無 — 同左 |

> **開發者注意**：兩種模式的 API 端點、參數、加密方式完全一致，無需為不同模式寫不同程式碼。
> 差異僅在綠界後台的合約設定與銀行閘道設定。不確定選哪個？**先用代收付模式**（門檻最低）。

#### 付款方式 × 金流服務 支援矩陣

> ⚠️ **SNAPSHOT 2026-03** | 來源：[developers.ecpay.com.tw](https://developers.ecpay.com.tw/) 開發者導覽首頁

##### 代收付模式 / 大特店模式（廠商僅與綠界簽約）

| 金流服務＼付款方式 | 信用卡一次付清 | 紅利折抵 | 分期付款 | 定期定額 | 銀聯卡 | Apple Pay | TWQR | 微信支付 | BNPL 無卡分期 | ATM | 超商代碼 | 超商條碼 |
|:---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| 全方位金流 AIO（guides/01） | ● | ● | ● | ● | ● | ● | ● | ● | ● | ● | ● | ● |
| 站內付 2.0 Web（guides/02） | ● | ● | ● | ● | ● | ● | | | | ● | ● | ● |
| 站內付 2.0 APP（guides/02） | ● | ● | ● | ● | ● | ● | | | | ● | ● | ● |
| 綁定信用卡（guides/02 §綁卡） | ● | | ● | | | | | | | | | |
| 非信用卡幕後取號（guides/03） | | | | | | | | | | ● | ● | ● |
| 信用卡幕後授權（guides/03） | ● | ● | ● | ● | ● | | | | | | | |

##### 新型閘道模式（廠商分別與銀行 + 綠界簽約）

> 新型閘道同時提供 **7 家銀行**的閘道服務，涵蓋市場約 **80%** 的信用卡，支援 3-24 期消費分期，並完整支援**國旅卡**與**美國運通卡 (AMEX)**。

| 金流服務＼付款方式 | 信用卡一次付清 | 紅利折抵 | 分期付款 | 定期定額 | 銀聯卡 | 美國運通 | Apple Pay | 國旅卡 |
|:---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| 全方位金流 AIO（guides/01） | ● | ● | ● | ● | ● | ● | ● | ● |
| 站內付 2.0 Web（guides/02） | ● | ● | ● | ● | ● | ● | ● | ● |
| 站內付 2.0 APP（guides/02） | ● | ● | ● | ● | ● | ● | ● | ● |
| 綁定信用卡（guides/02 §綁卡） | ● | | ● | | | ● | | |
| 信用卡幕後授權（guides/03） | ● | ● | ● | ● | ● | ● | | |

> ⚠️ 矩陣中空格表示「不支援」。BNPL 無卡分期由裕富 (URICH) / 銀角零卡 (Zingala) 提供，支援 3/6/9/18/24 期，最低消費金額 3,000 元。

#### 全服務端點速查

> 查找特定 API 端點？[guides/19 §3 全服務端點速查總表](./guides/19-http-protocol-reference.md) 提供 150+ 端點 × 7 個 Domain 的一頁總覽。

#### 金流決策樹

> 🎯 **第一次使用？從這裡開始**
>
> 如果開發者不確定需要什麼，請先問這三個問題：
> 1. **需要收款嗎？** → 是：見下方金流決策樹；否：跳到發票/物流/ECTicket決策樹
> 2. **消費者會看到付款畫面嗎？** → 是：AIO（guides/01）或站內付 2.0（guides/02）；否：幕後授權（guides/03）
> 3. **用 PHP 嗎？** → 是：直接用官方 SDK 範例；否：必讀 guides/13 + guides/14 + guides/19（⚠️ 兩份加密指南 URL encode 邏輯不同：AIO/物流用 guides/13 的 `ecpayUrlEncode`；站內付/幕後授權/發票用 guides/14 的 `aesUrlEncode`；不可混用）
>
> | 你的情境 | 建議路徑 | 預估時間 |
> |---------|---------|:-------:|
> | 只想先跑通第一筆測試交易 | [guides/00](./guides/00-getting-started.md) §概述 的「最快測試路徑」 | 30 分鐘 |
> | 要做完整電商（收款+發票+出貨） | [guides/11](./guides/11-cross-service-scenarios.md) 場景一 | 3-4 小時 |
> | 要串特定服務 | 使用下方決策樹導航 | 依服務而定 |

```
需要收款？
├── 不確定需要什麼 / 想做一個購物網站 → 讀 guides/00 + guides/11 場景一 [預計 1-2h]
├── 收款 + 發票 + 出貨（完整電商）→ 讀 guides/11 [預計 2-3h]
├── 消費者在網頁/App 付款
│   ├── 要綠界標準付款頁 → AIO（讀 guides/01）[預計 30m]
│   │   └── ⚠️ ReturnURL 有 10 秒超時限制，耗時邏輯需用佇列處理（見 guides/22）
│   ├── 要嵌入式體驗 → 站內付 2.0 — **首次串接讀 guides/02a（25-45 分鐘，非 PHP 約 45 分鐘）**；完整參考讀 guides/02（1 小時）
│   │   ├── ⚠️ 比 AIO 複雜：需手動處理 AES 加解密、雙 Domain 路由、ThreeDURL 判斷、兩種 Callback 格式
│   │   ├── 📋 **串接前先確認**（讀 guides/02a §首次串接快速路徑）：後端 ReturnURL/OrderResultURL 端點均可接收 POST、前端已備妥引入外部 JS 的頁面、AES 加密函式已備妥（非 PHP）
│   │   ├── ⚠️ **JS SDK 三依賴**：前端必須按順序載入 jQuery → node-forge → `/Scripts/sdk-1.0.0.js`（大寫 S），缺少任一個 SDK 會 throw Error
│   │   ├── ⚠️ 雙 Domain：GetTokenbyTrade/CreatePayment 走 `ecpg`，QueryTrade/DoAction 走 `ecpayment`（混用會 404）
│   │   ├── ⚠️ ThreeDURL 必判斷：CreatePayment 回應若含非空 ThreeDURL，前端**必須**導向 3D 驗證頁（2025/8 起幾乎必定出現，省略此步驟將導致交易逾時失敗）
│   │   ├── ⚠️ Callback 雙格式：ReturnURL 是 JSON POST（讀 `php://input`，回應 `1|OK`）；OrderResultURL 是 Form POST（讀 `$_POST['ResultData']`，不需回應 `1|OK`）
│   │   ├── ⚠️ ATM/CVS/Barcode：CreatePayment 後 Data 含付款指示（虛擬帳號/超商代碼）需顯示給消費者，ReturnURL **非同步**在消費者繳費後才到（見 guides/02 §非信用卡付款；**SPA/React/Vue 快速路徑** → [guides/02b](./guides/02b-ecpg-atm-cvs-spa.md)）
│   │   ├── ⚠️ Apple Pay：必須先完成域名驗證 + Merchant ID 申請 + 憑證上傳，按鈕才會顯示（見 guides/02 §Apple Pay 整合前置準備）
│   │   ├── ⚠️ Callback 同樣有 10 秒超時限制，耗時邏輯需用佇列處理（見 guides/22）
│   │   └── 🆘 **串接卡住？快速對照**：404→§14 | TransCode≠1→§15 | ThreeDURL沒處理→§16 | Callback格式→§17 | 全部不通→§18 | ATM/CVS沒收到ReturnURL→§30（guides/15）
│   ├── 不確定
│   │   ├── 前後端分離（React/Vue/Angular/SPA）→ 推薦站內付 2.0；如需 ATM/CVS 付款方式，讀 guides/02b（SPA 快速路徑）
│   │   └── 傳統 SSR / 簡單需求 → 推薦 AIO（最簡單、最常用）
│   └── 需要開發票？→ 是 → 同時讀 guides/04-invoice-b2c.md，callback 分開處理（見 guides/11）
├── 純後台扣款
│   ├── 信用卡 → 幕後授權（讀 guides/03）[預計 1h]
│   └── ATM/超商 → 幕後取號（讀 guides/03）[預計 1h]
├── 訂閱制 → AIO 定期定額（讀 guides/01 §定期定額）[預計 45m]
├── 信用卡分期 → AIO（ChoosePayment=Credit，CreditInstallment=3,6,12,18,24,30）（讀 guides/01 §分期範例）[預計 30m]
├── BNPL 先買後付 → AIO（ChoosePayment=BNPL，最低消費金額 3,000 元）（讀 guides/01）[預計 30m]
├── 綁卡快速付 → 站內付 2.0 綁卡（讀 guides/02 §綁卡付款流程）[預計 1h]
├── 實體門市刷卡 → POS 刷卡機（讀 guides/17-hardware-services.md §POS 刷卡機串接指引）[預計 2h]
├── 直播電商收款 → 直播收款（讀 guides/17-hardware-services.md §直播收款指引）[預計 1h]
├── Shopify → 購物車模組（讀 guides/10-cart-plugins.md #Shopify，API 規格見 references/Payment/Shopify專用金流API技術文件.md）
├── Mobile App（iOS/Android）→ 站內付 2.0（讀 guides/02c-ecpg-app-production.md + guides/23 Mobile App 區段）
├── Apple Pay → 優先站內付 2.0（完整 iOS SDK 支援，讀 guides/02c §Apple Pay）；AIO 亦可（ChoosePayment=ApplePay，讀 guides/01）[預計 30m-1h]
├── TWQR 行動支付 → AIO（ChoosePayment=TWQR）（讀 guides/01 §TWQR 範例）[預計 30m]
├── 微信支付 → AIO（ChoosePayment=WeiXin）（讀 guides/01 §微信支付範例）[預計 30m]
├── 銀聯卡
│   ├── 站內付 2.0 → ChoosePaymentList="6"，UnionPayInfo（讀 guides/02）[預計 1h]
│   └── AIO 信用卡頁面 → ChoosePayment=Credit，UnionPay=1（讀 guides/01 §信用卡一次付清參數）[預計 30m]
├── 非 PHP 語言完整範例 → 讀 guides/23-multi-language-integration.md（Go/Java/C#/TS/Kotlin/Ruby E2E + Mobile App）
├── 查詢訂單狀態 → AIO: guides/01 QueryTradeInfo 區段 / 站內付: guides/02 查詢區段 / 幕後授權: guides/03 查詢區段
├── 下載對帳檔 → guides/01 對帳區段（注意 domain 為 vendor.ecpay.com.tw）
├── 平台商多商戶（PlatformID）→ 特約合作模式，需另簽平台商合約。參數已含在 guides/01、guides/02 參數表中，搜尋 PlatformID
└── 其他 → 先讀 guides/00-getting-started.md 瞭解全貌
```

#### 物流決策樹

```
需要出貨？
├── 國內
│   ├── 超商取貨 → 國內物流 CVS（讀 guides/06-logistics-domestic.md）
│   ├── 宅配 → 國內物流 HOME（讀 guides/06-logistics-domestic.md）
│   └── 消費者自選 → 全方位物流（讀 guides/07-logistics-allinone.md）
├── 海外 → 跨境物流（讀 guides/08-logistics-crossborder.md）
└── 查詢物流狀態 → 國內: guides/06 §查詢物流訂單 / 全方位: guides/07 §查詢物流訂單 / 跨境: guides/08 §查詢
```

#### 電子發票決策樹

```
需要開發票？
├── 賣給消費者 → B2C（讀 guides/04-invoice-b2c.md）
│   ├── 延遲開立（先收款、滿足條件後才開） → guides/04 §DelayIssue（DelayFlag=1 手動觸發 / 2 自動排程）
│   ├── 折讓（退部分金額） → guides/04 §Allowance（線上 AllowanceByCollegiate 帶 CheckMacValue MD5）
│   └── 作廢（全額作廢） → guides/04 §Invalid
├── 賣給企業 → B2B（讀 guides/05-invoice-b2b.md）
│   ├── 交換模式（買受方確認） → guides/05 §Confirm
│   └── 折讓 / 作廢 → guides/05 §Allowance / §Invalid
├── 無網路環境 → 離線發票（讀 guides/18-invoice-offline.md）
└── 發票退款操作 → 見下方「退款/作廢/取消決策樹」
```

#### 電子收據決策樹

```
需要開立收據（非發票）？→ 讀 guides/25-receipt.md
├── 一般收據（押金、定金、雜支）→ ReceiptType=1（一般帳號 2000132）
├── 公益收據（捐給社福團體）→ ReceiptType=2（需綠界業務開通；僅可 1 項商品）
├── 政治獻金收據 → ReceiptType=4（需綠界業務開通；政治獻金帳號 3002607）
│   ├── 匿名 DonorType=5 → 金額 ≤ 10,000
│   └── 現金 PaymentMethod=3 → 金額 ≤ 100,000
├── 修改收據 → guides/25 §UpdateIssue
├── 作廢收據 → guides/25 §Invalid
├── 主動發送通知郵件 → guides/25 §Notification（商家→綠界）
├── 查詢單筆 → guides/25 §GetReceipt（ReceiptNo 或 RelateNumber 擇一）
└── AES-GCM 加密（選用）→ guides/25 §AES-GCM 模式
```

#### 其他決策樹

```
ECTicket？→ 讀 guides/09-ecticket.md
   測試帳號：官方提供公開測試帳號（見 guides/09 §測試帳號）
   適用場景：演唱會、電影票、餐券、遊樂園等虛擬票證
購物車平台？→ 讀 guides/10-cart-plugins.md
收款+發票+出貨？→ 讀 guides/11-cross-service-scenarios.md
PHP SDK 範例/用法？→ 讀 guides/12-sdk-reference.md
HTTP 協議細節（端點/認證/回應格式）？→ 讀 guides/19-http-protocol-reference.md
Callback/Webhook 接收架構？→ 讀 guides/21-webhook-events-reference.md（格式速查 + 各服務回應規格）+ guides/22（佇列化處理）
   ├── 何時主動查詢 vs 等 Callback？→ 信用卡即時付款：可用 QueryTradeInfo 主動查詢（guides/01 或 02），但仍須實作 Callback 作為最終確認；ATM/超商：必須等 Callback（付款非同步）
   ├── Callback 重試機制 → 綠界最多重送 4 次，須實作冪等處理（guides/21 §失敗恢復策略）
   └── 本機開發收不到 Callback？→ guides/24（ngrok / Cloudflare Tunnel 設定）
```

#### 退款/作廢/取消決策樹

```
需要退款或取消？
├── 信用卡退款
│   ├── AIO 訂單 → guides/01 DoAction（Action=R 退款 / Action=N 取消授權）
│   └── 站內付訂單 → guides/02 DoAction 區段
├── 非信用卡（ATM/超商代碼/條碼）→ ⚠️ 不支援 API 退款，需透過綠界商家後台或聯繫客服
├── 訂閱（定期定額）取消/暫停 → guides/01 §定期定額 CreditCardPeriodAction
├── 發票作廢 → guides/04 Invalid 區段（B2C）/ guides/05 Invalid 區段（B2B）
├── 發票折讓 → guides/04 Allowance 區段（B2C）/ guides/05 Allowance 區段（B2B）
├── 物流退貨 → guides/06 逆物流區段
└── 跨服務退款（付款+發票+物流）→ guides/11 補償動作對照表
```

#### 除錯決策樹

```
遇到問題？
├── CheckMacValue 驗證失敗 → 讀 guides/13 + guides/15 排查流程
├── AES 解密結果亂碼/失敗 → 讀 guides/14 常見錯誤 + 測試向量
├── 站內付 GetToken RtnCode ≠ 1（無明確錯誤訊息）→ **ConsumerInfo 物件缺失或 Email/Phone 未填**（讀 guides/02 ⓪ ConsumerInfo 規則）
├── 3D Secure 驗證相關
│   ├── 站內付 ThreeDURL 處理 → guides/02 §ThreeDURL（2025/8 起幾乎必出現，未導向 3D 頁面會逾時失敗）
│   └── AIO 3D 驗證 → 透明處理，消費者在綠界頁面完成，開發者無需額外實作
├── 收到錯誤碼 → 讀 guides/20 錯誤碼反向索引
├── Callback/Webhook 收不到 → 讀 guides/21 失敗恢復策略
├── 本機開發無法接收 Callback（localhost / 非標準 port）→ 讀 guides/24 tunneling 工具設定
├── 上線後交易異常 → 讀 guides/16 上線後觀察清單
├── 測試串接 → guides/00 Quick Start + 上方測試帳號，上線前逐項讀 guides/16 checklist
└── 不確定該讀哪份文件 → 讀 guides/00 總覽
```

> **⚡ 效能提醒**：預估日交易量 >1,000 筆、有高併發需求、或遇到 API 被限速（HTTP 403 Forbidden）？→ 請先讀 [guides/22](./guides/22-performance-scaling.md)（Rate Limiting 門檻值 + Callback 佇列架構 + 批次 API 最佳實踐）。

#### 快速指令（跨平台）

> **Claude Code**：將 `commands/` 內的 `.md` 檔複製到專案 `.claude/commands/` 即可使用 `/ecpay-*` 指令。
> **OpenAI GPTs**：已預設 4 個 Conversation Starters（見 SETUP.md §ChatGPT），最多 4 個按鈕。
> **Cursor**：無原生 slash 指令機制，直接用自然語言描述需求，AI 透過上方決策樹自動導航。
> **Copilot CLI**：無原生指令機制，以自然語言導航。

| 情境 | Claude Code `/` 指令 | 對應 guide |
|------|---------------------|------------|
| 串接金流（收款、查詢、退款、Callback） | `/ecpay-pay` | guides/01, 02, 03, 22 |
| 串接電子發票 | `/ecpay-invoice` | guides/04, 05, 18 |
| 串接物流（國內/全方位/跨境） | `/ecpay-logistics` | guides/06, 07, 08 |
| 串接ECTicket | `/ecpay-ecticket` | guides/09 |
| 除錯 + 加密驗證 | `/ecpay-debug` | guides/13, 14, 15, 21 |
| 上線前檢查 | `/ecpay-go-live` | guides/16 |

#### 快查表（問題→指南 / 需求→指南）

| 問題或需求 | 直接讀 |
|-----------|--------|
| CheckMacValue 驗證失敗 | guides/13 + guides/15 §1 |
| AES 解密結果亂碼 | guides/14 §常見錯誤 |
| Callback 收不到 | guides/15 §2 + guides/21 §Callback 回應格式速查 |
| 如何退款 | guides/01 §信用卡請款 / 退款 / 取消 / guides/02 §請款 / 退款 |
| 如何查訂單 | guides/01 §查詢訂單 / guides/02 §查詢 / guides/03 §查詢 |
| 如何對帳 | guides/01 §對帳（domain: vendor.ecpay.com.tw）|
| 如何開發票 | guides/04 (B2C) / guides/05 (B2B) |
| 處理 Callback / Webhook | guides/21（各服務 callback 回應格式彙總）|
| 測試帳號是什麼 | guides/00 §測試帳號 |
| 上線前檢查 / 切換正式環境 | guides/16 |
| 日交易 > 1,000 筆 / 高併發 / Rate Limiting | guides/22 §Rate Limiting + §Webhook 佇列架構 |
| 站內付 2.0 404 / Domain 打錯 | guides/02 端點表（ecpg vs ecpayment）+ guides/16 §URL 對照 |
| 站內付 GetTokenbyTrade RtnCode ≠ 1（無明確錯誤） | guides/02 §GetTokenbyTrade Data 必填欄位速查 — **ConsumerInfo 物件缺失或 Email/Phone 未填**（最常見根因）|
| AES-JSON 雙層錯誤檢查 | guides/20 §錯誤碼閱讀方式 + guides/04 §AES 請求格式 |
| 物流退貨 | guides/06 逆物流 / guides/07 逆物流 |
| 非 PHP 完整範例 | guides/23（⚠️ 使用 AI Section Index 行號跳轉） |
| PHP SDK 用法 / 不想手動加密 | guides/12（PHP 開發者可直接用 SDK，免手動實作 CheckMacValue / AES） |

### ⚠️ 兩種 URL Encode 不可混用

| 協議 | 使用函式 | 調用位置 | 混用後果 |
|------|---------|---------|---------|
| AIO 金流（CMV-SHA256）| `ecpayUrlEncode()` | CheckMacValue 計算 | 混用 `aesUrlEncode` → CheckMacValue 永遠不符 |
| ECPG / 發票 / 物流 v2（AES-JSON）| `aesUrlEncode()` | Data JSON 加密前 | 混用 `ecpayUrlEncode` → TransCode ≠ 1 |

> 兩者差異：`ecpayUrlEncode` 先 `urlencode` → `strtolower` → .NET 字元替換；`aesUrlEncode` 只做 `urlencode`（空格→`+`，`~`→`%7E`），無 lowercase、無 .NET 替換。詳見 [guides/14 §對比表](./guides/14-aes-encryption.md)。

### Callback 格式速查表

> 不同服務的 Callback 接收方式與回應格式不同，混淆是最常見的高頻錯誤。

| 服務 | Callback 類型 | 讀取方式 | 必要回應 | RtnCode 型別 |
|-----|:---:|---------|---------|:-----------:|
| AIO 金流（CMV-SHA256）| Form POST | `$_POST` / `req.body`（urlencoded） | 純文字 `1\|OK` | 字串 `'1'` |
| 國內物流（CMV-MD5）| Form POST | `$_POST` / `req.body`（urlencoded） | 純文字 `1\|OK` | 字串 `'1'` |
| ECPG ReturnURL（S2S）| JSON POST | `php://input` / `req.body`（json） | 純文字 `1\|OK` | 整數 `1` |
| ECPG OrderResultURL（前端）| Form POST + ResultData | `$_POST['ResultData']` → json_decode | HTML 結果頁（無需 `1\|OK`） | 整數 `1` |
| 信用卡幕後授權（S2S）| JSON POST | `php://input` / `req.body`（json） | 純文字 `1\|OK` | 整數 `1` |
| 非信用卡幕後取號（S2S）| JSON POST | `php://input` / `req.body`（json） | 純文字 `1\|OK` | 整數 `1` |
| 全方位物流 v2 | JSON POST | `php://input` / `req.body`（json） | AES 加密 JSON（三層結構） | 整數 `1` |
| B2C 電子發票（AllowanceByCollegiate 限定）| Form POST | `$_POST` / `req.body`（urlencoded） | 純文字 `1\|OK` | 字串 `'1'` |
| ECTicket | JSON POST | `php://input` / `req.body`（json） | AES 加密 JSON + CheckMacValue | 整數 `1` |
| 直播收款（ReturnURL）| JSON POST | `php://input` / `req.body`（json） | 純文字 `1\|OK`（⚠️ 請求格式同ECTicket，但回應不同） | 整數 `1` |

> 完整 Callback 回應規則見 AI 注意事項「不可省略 Callback 回應」段落。

### 語言陷阱速查表

> 非 PHP 開發者必讀。完整實作見 [guides/13](./guides/13-checkmacvalue.md) + [guides/14](./guides/14-aes-encryption.md)。
> 📌 **語言慣例**：生成目標語言程式碼時，同時載入 `guides/lang-standards/{語言}.md`（如 [python.md](./guides/lang-standards/python.md)、[go.md](./guides/lang-standards/go.md)），確保命名、型別、錯誤處理符合該語言慣例。

| 語言 | 最常見 Bug | 解決方案 | 詳細位置 |
|------|-----------|---------|---------|
| Python | `quote_plus()` 不編碼 `~` | 手動替換 `~` → `%7e`（CMV）或 `%7E`（AES） | guides/13 §Python |
| Node.js | `encodeURIComponent()` 空格為 `%20` 非 `+` | 替換 `%20` → `+` | guides/13 §Node.js |
| Java | CMV: `HashMap` 不保證 key 順序。AES: `URLEncoder.encode` 不編碼 `!*` | CMV: 用 `TreeMap`。AES: 補 `.replace("!", "%21").replace("*", "%2A")` | guides/13 §Java, guides/14 §Java |
| C# | CMV: `WebUtility.UrlEncode` 不編碼 `~`。AES: `JsonSerializer` 預設轉義 `<>&+'` | CMV: 補 `~→%7e`。AES: 用 `UnsafeRelaxedJsonEscaping` | guides/13 §C#, guides/23 §C# |
| Go | CMV: `url.QueryEscape` 不編碼 `~`（需補 `~→%7e`）。AES: `url.QueryEscape` 不編碼 `~`（需補 `~→%7E`）；`json.Marshal` 預設轉義 `<>&` | CMV: 補 `~→%7e`。AES: 補 `~→%7E`；`SetEscapeHTML(false)` | guides/13 §Go, guides/23 §Go |
| Kotlin | CMV: `URLEncoder.encode` 不編碼 `~`。AES: 不編碼 `!*` | CMV: 補 `~→%7e`。AES: 補 `!→%21`, `*→%2A` | guides/13 §Kotlin, guides/14 §Kotlin |
| Ruby | `CGI.escape` vs `URI.encode_www_form_component` 行為不同 | 使用 `CGI.escape`，替換 `~` → `%7e` | guides/13 §Ruby |
| Rust | `form_urlencoded` crate 不編碼 `~` | 手動替換 `~` → `%7e`（CMV）或 `%7E`（AES） | guides/13 §Rust |
| Swift | `addingPercentEncoding` 不編碼 `+` 和 `~` | 使用自訂 `CharacterSet`，補編碼 `+~` | guides/13 §Swift |
| TypeScript | 同 Node.js | 同 Node.js | guides/13 §TypeScript |
| C/C++ | `curl_easy_escape` 行為因 libcurl 版本不同（7.x vs 8.x 對 `~` 處理有差異） | 使用 guides/13 §C 的自訂 `ecpay_url_encode()` 實作，不依賴 libcurl 版本 | guides/13 §C |

### AI 注意事項（不可做的事）

- **不可將 ECPG 等同於站內付 2.0**：ECPG 是 EC Payment Gateway 的簡稱，代表綠界的**線上金流服務**，涵蓋站內付 2.0、綁定信用卡、幕後授權等多項服務；站內付 2.0 只是其中之一。POS 刷卡機屬於**線下金流服務**，與 ECPG 平行而非從屬。同理，代收付模式（大特店模式）和新型閘道模式是合約模式，不可自行發明英文名稱（如 ~~"ECPG Model"~~、~~"General Model"~~）
- **不可使用 iframe** 嵌入綠界付款頁（會被擋，使用站內付 2.0 或新視窗）
- **不可混用** CMV 的 `ecpayUrlEncode` 和 AES 的 `aesUrlEncode`（兩者邏輯不同，見 guides/14 對比表）
- **不可假設所有 API 回應都是 JSON**（AIO 回 HTML/URL-encoded/pipe-separated）
- **不可在前端或版本控制中暴露** HashKey/HashIV
- **不可將 ATM RtnCode=2 或 CVS RtnCode=10100073 視為錯誤**（代表取號成功，消費者尚未付款）
- **生成程式碼或回答 API 規格問題時，必須 web_fetch references/ 中的對應 URL**：不可僅依賴 guides/ SNAPSHOT 或 AI 自身記憶回答。唯一可省略 web_fetch 的情況是：(1) 純概念說明且不涉及具體參數值，或 (2) web_fetch 失敗後的備援（但必須告知使用者）
- **URL 來源白名單（強制）**：回覆中引用的所有 ECPay 技術文件 URL **必須來自 references/ 檔案中列出的 443 個 URL**。禁止引用 AI 記憶中的 URL、第三方部落格、Stack Overflow、或任何非 `developers.ecpay.com.tw` 網域的連結作為 API 規格來源。若需要的 URL 不在 references/ 中，應告知使用者「此資訊未收錄於官方索引，建議至 developers.ecpay.com.tw 搜尋確認」
- **生成程式碼時必須標註資料來源**：在程式碼註解中標明參數值取自 SNAPSHOT 或 web_fetch（例如 `// Source: web_fetch references/Payment/... 2026-03-06`），方便開發者日後驗證
- **不可將 ECPG 所有端點都打向 ecpg domain**（查詢/請退款走 `ecpayment`；Token 類及 CreatePayment 走 `ecpg`，詳見 guides/02 端點表）
- **不可省略 Callback 回應**：CMV-SHA256（AIO）回 `1|OK`、**站內付 2.0 ReturnURL** 回 `1|OK`（官方規格 9058.md）、**站內付 2.0 OrderResultURL** 回 HTML 頁面（前端跳轉，不重試）、信用卡幕後授權回 `1|OK`（官方規格 45907.md）、非信用卡幕後取號回 `1|OK`、國內物流 CMV-MD5 回 `1|OK`、全方位/跨境物流 v2 回 **AES 加密 JSON**（三層結構）、ECTicket回 **AES 加密 JSON + CheckMacValue**（Data 內 `{"RtnCode": 1, "RtnMsg": "成功"}`）、**直播收款** 回 `1|OK`（⚠️ callback 格式與ECTicket相同：JSON POST + AES 解密 Data + ECTicket 式 CheckMacValue SHA256；但回應為純文字 `1|OK`，與ECTicket不同）、**B2C 發票線上折讓（AllowanceByCollegiate）回 `1|OK`**（⚠️ Callback 為 Form POST + CheckMacValue **MD5**，是發票中唯一帶 CheckMacValue 的 API，詳見 [guides/04](./guides/04-invoice-b2c.md)）。**`1|OK` 常見錯誤格式**（會導致系統重發 4 次）：`"1|OK"`（含引號）、`1|ok`（小寫 ok）、`1OK`（缺分隔）、帶空白或換行
- **AES-JSON API 必須做雙層錯誤檢查**：先查 `TransCode`（傳輸層），再查 `RtnCode`（業務層）。僅 `TransCode == 1` 且 `RtnCode` 為成功值時交易才真正成功（詳見 [guides/20](./guides/20-error-codes-reference.md) §TransCode vs RtnCode）。**ECTicket須做三層檢查**：TransCode → 解密 Data → 驗證 CheckMacValue → RtnCode（詳見 [guides/09](./guides/09-ecticket.md)）
- **不可使用 TWD 以外的幣別**（ECPay 僅支援新台幣）
- **超出範圍**：若功能不在本 Skill 覆蓋範圍或需要未支援的語言，告知使用者聯繫綠界客服 (02-2655-1775) 或參考最接近的語言實作翻譯
- **不可在 ItemName / TradeDesc 中放入系統指令關鍵字**（echo、python、cmd、wget、curl、ping、net、telnet 等約 40 個），綠界 CDN WAF 會直接攔截請求，回傳非預期的錯誤頁面
- **ItemName 超過 400 字元會被截斷**：截斷處的 UTF-8 多位元組字元會產生亂碼，導致綠界端計算的 CheckMacValue 與特店端不一致 → 掉單。建議送出前先截斷至安全長度再計算 CMV
- **ReturnURL / OrderResultURL 僅支援 port 80（HTTP）和 443（HTTPS）**：開發環境常用的 :3000、:5000、:8080 等非標準 port 無法收到 callback。本機開發需使用 ngrok 等工具轉發。**亦不可放在 CDN（CloudFlare、Akamai 等）後方**——CDN 會改變來源 IP 或攔截非瀏覽器請求，導致 callback 失敗
- **LINE / Facebook App 內建 WebView 會導致付款失敗**：WebView 無法正確 POST form 至綠界 → MerchantID is Null。需引導消費者用外部瀏覽器開啟付款連結
- **ReturnURL、OrderResultURL、ClientBackURL 用途不同，不可設為同一網址**：ReturnURL = Server 端背景通知（須回 `1|OK`）；OrderResultURL = Client 端前景導轉（顯示給消費者）；ClientBackURL = 僅導回頁面（不帶任何付款結果）
- **Callback 回應的 HTTP Status 必須是 200**：回傳 201、202、204 等非 200 狀態碼，綠界一律視為失敗並觸發重試。即使 body 正確（如 `1|OK`）也無效
- **RtnCode 型別依協議而異（常見錯誤來源）**：
  - **CMV 類服務**（AIO 金流 Callback、國內物流 Callback）→ Form POST，`RtnCode` 為**字串**（如 `"1"`、`"2"`、`"10100073"`），需用字串比較 `=== '1'`
  - **AES-JSON 類服務**（ECPG 線上金流〔含站內付 2.0、幕後授權〕、發票、全方位物流 v2、ECTicket）→ JSON 解密後，`RtnCode` 為**整數**（如 `1`），應用整數比較 `=== 1`；用字串嚴格比較 `=== '1'` 永遠為 false
  - 防禦性寫法（跨服務兼容）：`Number(rtnCode) === 1` / `int(rtn_code) == 1`，但建議按服務類型使用正確型別比較
- **ATM / 超商代碼 / 條碼付款有兩個 Callback**：第一個通知到 `PaymentInfoURL`（取號成功，RtnCode=2 或 10100073），第二個通知到 `ReturnURL`（實際付款成功，RtnCode=1）。必須同時實作兩個端點，漏掉 PaymentInfoURL 會導致消費者拿不到繳費資訊
- **加密/解密每一步都必須驗證**：(1) AES 加密前確認 JSON 序列化正確（key 順序、無 HTML escape）；(2) AES 解密後確認得到合法 JSON（非 null/空字串）；(3) Base64 必須使用**標準 alphabet**（`+/=`），不可使用 URL-safe alphabet（`-_`）；(4) 若啟用 `NeedExtraPaidInfo=Y`，Callback 額外回傳的欄位**全部**必須納入 CheckMacValue 驗證（非 PHP 語言手動計算時最易遺漏）
- CheckMacValue 驗證**禁止使用 `==` / `===`**，必須使用 timing-safe 函式 → [guides/13 §timing-safe](./guides/13-checkmacvalue.md)
- **DoAction（請款/退款/取消）僅適用於信用卡**：ATM、超商代碼、條碼付款為消費者臨櫃/轉帳付現，**不支援線上退款 API**。若開發者要求退款，必須先確認原交易的 `PaymentType` — 僅信用卡類（`Credit_CreditCard`）可呼叫 `/CreditDetail/DoAction`（Action=R），其他付款方式需透過綠界商家後台人工處理或聯繫客服
- **Callback 必須實作冪等（Idempotency）與重放保護**：綠界 Callback 可能因網路異常重送最多 4 次。處理邏輯應以 `MerchantTradeNo` 為 key 做 upsert（非 insert），避免重複入帳或重複出貨。建議同時檢查 `PaymentDate` 與系統時間差異，過大時記錄警告。實作建議：使用 `SELECT ... FOR UPDATE`（PostgreSQL/MySQL）或 unique constraint + upsert 確保同一 MerchantTradeNo 不會因併發 Callback 造成重複入帳
- **送出前驗證與消毒所有使用者輸入**：`ItemName`、`TradeDesc` 應過濾 HTML 標籤與控制字元（`\x00-\x1F`）；`MerchantTradeNo` 應限制為英數字（長度上限 20 字元）；金額 `TotalAmount` 必須為正整數。不做驗證可能觸發 WAF 攔截或 CheckMacValue 不符
- **MerchantTradeDate 必須使用 UTC+8 時區**：格式為 `yyyy/MM/dd HH:mm:ss`。伺服器若在海外或使用 UTC，必須先轉換為台灣時間，否則 ECPay 會拒絕超過允許時差的訂單
- **比對 RtnCode 時建議使用防禦性轉型**：`Number(rtnCode) === 1`（JavaScript）或等效寫法，避免因字串/數字型別差異導致判斷錯誤。AIO/國內物流 Callback 的 RtnCode 為字串 `'1'`，ECPG/發票解密後為整數 `1`
- **語言強制規則**：見文件頂部 CRITICAL 區塊（本規則優先順序最高）

> **AI 注意**：大多數請求只需載入 SKILL.md + 1-2 份 guide。
> **guides/ 參數表為 SNAPSHOT（2026-03）**—��穩定度高（改動機率 < 5%），可作為流程理解的參考。
> **預設行為：有 web_fetch 能力時，一律先從 references/ 取得即時規格再回答。** guides/ 僅作為 web_fetch 失敗時的備援，且必須告知使���者資料來自 SNAPSHOT。
> **唯一可省略 web_fetch**：純概念說明（如「什麼是站內付？」）且���涉及具體參數值、端點路徑、或程式碼生成。
> guides/13、14、23 有 AI Section Index（行號索引），若只需單一語言可用 offset/limit 讀取特定行範圍。
> AES vs CMV 對比表見 guides/14 §AES vs CMV URL Encode 對比表（line 129-226）。
> guides/23 有約 1700 行，建議使用 AI Section Index 的行號範圍只讀取目標語言的 E2E 區段。

### 步驟 2.5：確認 HTTP 協議規格（非 PHP 語言必讀）

在翻譯 PHP 範例之前，**必須先讀 `guides/19-http-protocol-reference.md`**，確認目標 API 使用的：

1. **協議模式**（CMV-SHA256/AES-JSON/AES-JSON+CMV/CMV-MD5）— 決定 Content-Type 和認證方式
2. **端點 URL**（測試/正式）— 確認精確路徑
3. **回應格式**（pipe-separated/URL-encoded/JSON/HTML/CSV）— 決定解析邏輯
4. **認證細節**（SHA256/MD5/AES）— 引用 guides/13 或 guides/14 的演算法

> ⚠️ PHP SDK 的 Service 類別已封裝所有 HTTP 細節。
> 非 PHP 語言必須自行處理：HTTP 請求構造、Content-Type 設定、CheckMacValue/AES 計算、回應解析。
> 切勿假設所有 API 使用相同的請求/回應格式。

### 步驟 3：程式碼生成

1. 讀取 `guides/` 中對應指南，取得整合流程和架構邏輯
2. 讀取 `scripts/SDK_PHP/example/` 中對應的 PHP 範例
3. **從 references/ 即時讀取對應 API 的最新規格**：讀取 reference 檔案 → 找到對應章節 URL → web_fetch 取得最新參數表，以確保端點路徑、參數名稱、必填規則、回應格式為最新
4. **摘取 API 頁面中的所有 ⚠ 注意事項**：web_fetch 取得的頁面包含注意事項段落（若存在），必須在回覆或程式碼註解中主動告知開發者
5. **注意不同付款方式/服務之間的語意差異**：相同參數名在不同服務中可能有不同單位（如 `StoreExpireDate` 在超商代碼=分鐘、條碼=天）、不同最低金額（BNPL ≥ 3000）、不同回傳值（`PaymentType` 回傳 `Credit_CreditCard` ≠ 送出的 `Credit`）、不同 Content-Type（金流=form-urlencoded、發票=json）。讀取 API 頁面時必須注意這些隱含差異
6. **Timestamp 一律使用 Unix 秒數**（非毫秒）：JavaScript `Date.now()` 回傳毫秒，必須除以 1000 並取整
7. **首次串接某服務時**（本次對話中第一次涉及該服務），同時 web_fetch 該服務的「介接注意事項」頁面（見下方 [§介接注意事項 URL 速查表](#介接注意事項-url-速查表)），摘取所有關鍵限制告知開發者
8. **載入目標語言的程式規範**：如果開發者不用 PHP，翻譯前**先**讀取 `guides/lang-standards/{語言}.md`，遵循其命名慣例、型別定義、錯誤處理、HTTP Client 設定、Callback Handler 模板等規範，確保產出的程式碼為 idiomatic 且生產就緒
9. 將 PHP 範例翻譯為目標語言，翻譯時保留所有參數名、端點 URL、加密邏輯
10. 加密實作依服務類型參考：CMV 服務（AIO/國內物流）→ `guides/13-checkmacvalue.md`；AES 服務（站內付/幕後授權/發票/物流v2）→ `guides/14-aes-encryption.md`；ECTicket → 兩者都需要（AES 加密 + CMV 簽名）；⚠️ **兩者 URL encode 函式邏輯不同，不可混用**
11. HTTP 協議細節參考 `guides/19-http-protocol-reference.md`（端點 URL、回應格式、認證方式）
12. 標註原始範例路徑供開發者查閱

> 💡 **非 PHP 開發者**：生成程式碼時同時讀取 `guides/lang-standards/{語言}.md`，確保產出的程式碼符合該語言慣例（命名規則、錯誤處理、HTTP Client 設定等）。

> **語言規範檔案對照**：`python.md` · `nodejs.md` · `typescript.md` · `java.md` · `csharp.md` · `go.md` · `kotlin.md` · `ruby.md` · `rust.md` · `swift.md` · `c.md` · `cpp.md` — 均位於 `guides/lang-standards/` 目錄

### 步驟 4：測試驗證

- 提供測試環境帳號（見下方快速參考）
- 引導使用模擬付款功能
- 提醒上線前切換帳號
- 使用 [test-vectors/checkmacvalue.json](./test-vectors/checkmacvalue.json) 驗證 CheckMacValue 實作正確性
- 使用 [test-vectors/aes-encryption.json](./test-vectors/aes-encryption.json) 驗證 AES 加密實作正確性

### 步驟 5：上線檢查

- 讀取 `guides/16-go-live-checklist.md` 逐項檢查

### 程式碼翻譯品質準則

翻譯 PHP 範例為其他語言時：
1. 翻譯後程式碼必須可直接編譯/執行
2. 使用該語言 2024-2025 年的慣用寫法
3. 必須包含套件管理器安裝命令
4. 必須包含最低版本需求
5. 不變項：端點 URL、參數名、JSON 結構、加密邏輯、Callback 回應格式（見 [guides/21](./guides/21-webhook-events-reference.md)）
6. **拆解 PHP SDK 封裝層**：PHP SDK 的 Service 類別隱藏了大量 HTTP 細節。翻譯前必須逐一確認：
   - `$_POST` / `$_GET` 背後的 **Content-Type** 是什麼（form-urlencoded vs JSON）
   - SDK 方法背後的實際 **HTTP 請求方式**（endpoint、headers、body 格式）
   - 回傳值的**實際型態**（字串 vs 物件 vs 陣列）
   - SDK 內建處理的**隱含行為**（如 3D Secure 跳轉、自動解密、錯誤重試）
   
   > 這些隱含行為不會出現在 API 文件中，必須從 PHP 範例程式碼和 `scripts/SDK_PHP/` 原始碼推斷。

### 語言特定陷阱（速查）

> 完整對照表見 [guides/13](./guides/13-checkmacvalue.md)、[guides/14](./guides/14-aes-encryption.md)、[guides/23 §JSON 序列化全語言對照](./guides/23-multi-language-integration.md)。

**翻譯 PHP 為其他語言時，最關鍵的三個陷阱**：

1. **AES vs CMV URL-encode 邏輯不同**（全非 PHP 語言）— AES 不做 `toLowerCase` 和 `.NET 字元還原`，見 guides/14 §AES vs CMV 對比表
2. **空格編碼為 `%20` 而非 `+`**（Node.js, TypeScript, C, Swift, Rust）— 編碼後替換 `%20` → `+`
3. **`~` 未被編碼**（全非 PHP 語言）— 手動替換 `~` → `%7E`

> 其他陷阱（PKCS7 padding、JSON key 順序、compact JSON、`'` 編碼、HTML 轉義、hex 大小寫、timing-safe 比較）：見 guides/14 各語言章節。

### AI 常犯錯誤清單（生成程式碼前自檢）

| # | 錯誤 | 後果 | 防範 |
|---|------|------|------|
| 1 | 混用 `ecpayUrlEncode`（CMV）與 `aesUrlEncode`（AES） | CheckMacValue 永遠不符 | 確認當前 API 協定後選用正確函式 |
| 2 | AES Base64 使用 URL-safe alphabet（`-_`） | 解密失敗 | 明確指定標準 alphabet（`+/=`） |
| 3 | Callback 回 `1\|OK` 格式錯誤（帶引號/小寫/換行） | 觸發最多 4 次重試 | 回傳精確 ASCII `1\|OK`，HTTP 200 |
| 4 | CheckMacValue 用 `==` 比對（非 timing-safe） | Timing attack 風險 | 使用語言對應的 timing-safe 函式 |
| 5 | 將 ATM `RtnCode=2` / CVS `RtnCode=10100073` 視為錯誤 | 訂單誤取消 | 取號成功碼≠付款成功碼 |
| 6 | 站內付 2.0 所有請求打同一 domain | 404 錯誤 | Token/CreatePayment→`ecpg`；查詢/退款→`ecpayment` |
| 7 | 使用 iframe 嵌入付款頁 | 瀏覽器封鎖 | 用站內付 2.0 或 `window.location.href` |
| 8 | `RtnCode` 型別比對錯誤（字串 vs 整數） | 判斷永遠失敗 | CMV 協定→字串；AES-JSON→整數 |
| 9 | 對非信用卡付款呼叫 DoAction 退款 | API 回錯誤 | 先檢查 PaymentType |
| 10 | ItemName 含系統關鍵字（echo、curl 等） | WAF 攔截 10400011 | 僅放商品名稱 |
| 11 | JS SDK `initialize` 傳整數（0/1）而非字串 | SDK 靜默失敗或連錯環境 | `ECPay.initialize('Stage', 1, cb)`（測試）或 `'Prod'`（正式） |
| 12 | 自訂容器 ID（如 `payment-form`） | 付款表單不渲染 | 必須使用固定 `<div id="ECPayPayment">`，SDK 硬編碼此 ID |
| 13 | 直接讀 `data.ThreeDURL`（扁平化）而非 `data.ThreeDInfo.ThreeDURL`（巢狀） | ThreeDURL 永遠取不到，交易逾時 | CreatePayment 回應為巢狀結構，後端需 `data['ThreeDInfo']['ThreeDURL']` |

## 快速參考

### 環境 URL

| 服務 | 測試環境 | 正式環境 |
|------|---------|---------|
| 金流 AIO | payment-stage.ecpay.com.tw | payment.ecpay.com.tw |
| 站內付 2.0 Token / 建立交易（ecpg domain） | ecpg-stage.ecpay.com.tw | ecpg.ecpay.com.tw |
| ECPG 查詢 / 授權 / 請退款（ecpayment domain） | ecpayment-stage.ecpay.com.tw | ecpayment.ecpay.com.tw |
| 物流 | logistics-stage.ecpay.com.tw | logistics.ecpay.com.tw |
| 電子發票 | einvoice-stage.ecpay.com.tw | einvoice.ecpay.com.tw |
| ECTicket | ecticket-stage.ecpay.com.tw | ecticket.ecpay.com.tw |
| 直播收款 | ecpayment-stage.ecpay.com.tw | ecpayment.ecpay.com.tw |
| 特店後台 | vendor-stage.ecpay.com.tw | vendor.ecpay.com.tw |

### 測試帳號

> ⚠️ **安全警告**：以下為**公開共用**測試帳號，所有開發者共用相同帳號。
> - **禁止用於正式環境**：正式環境務必使用專屬帳號
> - **禁止寫入版本控制**：正式環境的 HashKey/HashIV 必須以環境變數管理
> - 共用帳號的測試交易可能被其他開發者看到，不影響開發

| 用途 | MerchantID | HashKey | HashIV | 加密 |
|------|-----------|---------|--------|------|
| 金流 AIO | 3002607 | pwFHCqoQZGmho4w6 | EkRm7iFT261dpevs | SHA256 |
| ECPG 線上金流（站內付 2.0 / 幕後授權 / 幕後取號） | 3002607 | pwFHCqoQZGmho4w6 | EkRm7iFT261dpevs | AES |
| 國內物流 B2C | 2000132 | 5294y06JbISpM5x9 | v77hoKGq4kWxNNIS | MD5 |
| 國內物流 C2C | 2000933 | XBERn1YOvpM9nfZc | h1ONHk4P4yqbl5LK | MD5 |
| 全方位/跨境物流 | 2000132 | 5294y06JbISpM5x9 | v77hoKGq4kWxNNIS | AES |
| 電子發票 | 2000132 | ejCk326UnaZWKisg | q9jcZX8Ib9LM8wYk | AES |
| 離線電子發票 | 3085340 | HwiqPsywG1hLQNuN | YqITWD4TyKacYXpn | AES |
| 電子收據（一般/公益）| 2000132 | ejCk326UnaZWKisg | q9jcZX8Ib9LM8wYk | AES-CBC / AES-GCM |
| 電子收據（政治獻金）| 3002607 | pwFHCqoQZGmho4w6 | EkRm7iFT261dpevs | AES-CBC / AES-GCM |
| ECTicket（特店） | 3085676 | 7b53896b742849d3 | 37a0ad3c6ffa428b | AES + CMV |
| ECTicket（平台商） | 3085672 | b15bd8514fed472c | 9c8458263def47cd | AES + CMV |
| ECTicket（價金保管-使用後核銷） | 3362787 | c539115ea7674f20 | 86f625e60cb1473a | AES + CMV |
| ECTicket（價金保管-分期核銷） | 3361934 | 1069c84afab54f16 | 795c968d90c14971 | AES + CMV |
| 國內物流（備用，非 OTP 模式） | 2000214 | 5294y06JbISpM5x9 | v77hoKGq4kWxNNIS | MD5 |

> ⚠️ ECTicket的 HashKey/HashIV 與金流**不同**，請使用對應的介接資訊。
> 三種ECTicket模式（純發行、價金保管-使用後核銷、價金保管-分期核銷）使用不同帳號，切勿混用。分期核銷不支援平台商。詳見 guides/09 §測試帳號。

> **常見錯誤：帳號混用** — 金流、物流、發票使用**不同的** MerchantID 和 HashKey/HashIV。
> 同時串接多個服務時，請確認每個 API 呼叫使用對應服務的帳號，混用會導致 CheckMacValue 驗證失敗。

> **物流備用帳號（非 OTP 模式）**：MerchantID `2000214`（HashKey/HashIV 同 `2000132`），適用於特定不需 OTP 驗證的物流測試情境。一般開發以 `2000132` 為主；若 API 文件指定使用非 OTP 帳號時才切換。

### 3D 驗證 SMS 碼：`1234`

### 測試信用卡號

| 卡別 | 卡號 | 用途 |
|------|------|------|
| VISA（國內） | 4311-9522-2222-2222 | 一般測試 |
| VISA（國內） | 4311-9511-1111-1111 | 一般測試 |
| VISA（國際） | 4000-2011-1111-1111 | 國際卡測試 |
| 美國運通（國內） | 3403-532780-80900 | AMEX 測試（限閘道商，即直接與銀行介接的大型特店） |
| 美國運通（國際） | 3712-222222-22222 | AMEX 國際測試（限閘道商） |
| 永豐 30 期 | 4938-1777-7777-7777 | 永豐信用卡分期測試 |

- 安全碼：任意三碼數字（如 222）
- 有效期限：任意大於當前月年的值
- 3D Secure 驗證碼：`1234`（測試環境固定，不需接收簡訊）

> 平台商模式（PlatformID）測試帳號：AIO 3002599、ECPG 3003008

### SDK 安裝

```bash
composer require ecpay/sdk
```

### 重要提醒

- TLS 1.2 必須
- 3D Secure 2.0：已於 2025/8 起強制實施
- ChoosePayment=ALL 可用 IgnorePayment 排除特定付款方式
- Postback URL 使用 FQDN 而非固定 IP

> 其餘關鍵規則（iframe 禁用、ReturnURL `1|OK` 格式、HashKey 禁前端、API 限速、port 限制等）詳見上方「AI 注意事項」段落。

### 介接注意事項 URL 速查表

> ⚠️ **AI 必讀**：首次串接某服務時（本次對話中第一次涉及該服務），**必須 web_fetch 對應的介接注意事項頁面**，摘取所有注意事項主動告知開發者。

| 服務 | 介接注意事項 URL |
|------|----------------|
| AIO 金流 | https://developers.ecpay.com.tw/2858.md |
| 站內付 2.0 (Web) | https://developers.ecpay.com.tw/8987.md |
| 站內付 2.0 (App) | https://developers.ecpay.com.tw/9168.md |
| 國內物流 | https://developers.ecpay.com.tw/7400.md |
| 全方位物流 | https://developers.ecpay.com.tw/10092.md |
| 跨境物流 | https://developers.ecpay.com.tw/8291.md |
| B2C 電子發票 | https://developers.ecpay.com.tw/7854.md |
| B2B 電子發票（存證模式）| https://developers.ecpay.com.tw/24176.md |
| 離線 POS 電子發票 | https://developers.ecpay.com.tw/13768.md |
| 電子收據 | https://developers.ecpay.com.tw/64230.md |
| ECTicket（純發行） | https://developers.ecpay.com.tw/29916.md |
| ECTicket（價金保管，首選） | https://developers.ecpay.com.tw/40322.md |
| 信用卡幕後授權 | https://developers.ecpay.com.tw/45901.md |
| 非信用卡幕後取號 | https://developers.ecpay.com.tw/27984.md |
| Shopify | https://developers.ecpay.com.tw/29070.md |
| 直播收款 | https://developers.ecpay.com.tw/41022.md |

### 已知限制

- 僅支援新台幣（TWD）交易
- 不支援分帳功能（Split Payment）——ECPay 目前無分帳 API，需自行在應用層處理拆帳邏輯
- references/ URL 索引需要網路連線才能即時讀取最新 API 規格
- OpenAI GPTs 無法直接讀取 references/ 檔案（透過 Web Search 替代，可靠性略低於 web_fetch 直讀）
- AI 翻譯品質可能因模型與語言組合而異，生成的程式碼片段應經人工驗證

## 文件索引

> **大多數專案只需閱讀 2-3 份指南（共 30-60 分鐘）。** 共 29 份指南，使用上方決策樹找到你需要的，無需全部閱讀。
> guides/13 + guides/14 各需 20-30 分鐘（非 PHP 必讀）。guides/19 + guides/20 共 20 分鐘（協議細節 + 錯誤碼）。

### 深度指南（guides/）

**入門與全覽**

| # | 檔案 | 主題 | 預估閱讀 |
|---|------|------|:-------:|
| 00 | guides/00-getting-started.md | 從零開始：第一筆交易到上線 | 15 分鐘 |
| 11 | guides/11-cross-service-scenarios.md | 跨服務整合場景 | 20 分鐘 |

**金流**

| # | 檔案 | 主題 | 預估閱讀 |
|---|------|------|:-------:|
| 01 | guides/01-payment-aio.md | 全方位金流 AIO（20 個 PHP 範例） | 25 分鐘 |
| 02 | guides/02-payment-ecpg.md | 站內付 2.0 hub（概述 + 付款流程 + 綁卡/查詢/對帳） | 20 分鐘 |
| 02a | guides/02a-ecpg-quickstart.md | 站內付首次串接 + Python/Node.js 完整範例 | 25 分鐘 |
| 02b | guides/02b-ecpg-atm-cvs-spa.md | ATM/CVS 快速路徑 + SPA 整合 | 10 分鐘 |
| 02c | guides/02c-ecpg-app-production.md | App 整合 + Apple Pay + 正式環境 | 10 分鐘 |
| 03 | guides/03-payment-backend.md | 幕後授權 + 幕後取號 | 20 分鐘 |
| 17 | guides/17-hardware-services.md | 硬體與專用服務指引（POS 刷卡機 + 直播收款） | 15 分鐘 |

**電子發票**

| # | 檔案 | 主題 | 預估閱讀 |
|---|------|------|:-------:|
| 04 | guides/04-invoice-b2c.md | B2C 電子發票（19 個 PHP 範例） | 25 分鐘 |
| 05 | guides/05-invoice-b2b.md | B2B 電子發票（23 個 PHP 範例） | 25 分鐘 |
| 18 | guides/18-invoice-offline.md | 離線電子發票指引 | 15 分鐘 |
| 25 | guides/25-receipt.md | 電子收據（一般/公益/政治獻金，AES-CBC + AES-GCM） | 20 分鐘 |

**物流**

| # | 檔案 | 主題 | 預估閱讀 |
|---|------|------|:-------:|
| 06 | guides/06-logistics-domestic.md | 國內物流（24 個 PHP 範例） | 25 分鐘 |
| 07 | guides/07-logistics-allinone.md | 全方位物流（16 個 PHP 範例） | 20 分鐘 |
| 08 | guides/08-logistics-crossborder.md | 跨境物流（8 個 PHP 範例） | 15 分鐘 |

**其他服務**

| # | 檔案 | 主題 | 預估閱讀 |
|---|------|------|:-------:|
| 09 | guides/09-ecticket.md | ECTicket | 15 分鐘 |
| 10 | guides/10-cart-plugins.md | 購物車模組 | 10 分鐘 |

**跨領域技術參考**

| # | 檔案 | 主題 | 預估閱讀 |
|---|------|------|:-------:|
| 12 | guides/12-sdk-reference.md | PHP SDK 完整參考 | 15 分鐘 |
| 13 | guides/13-checkmacvalue.md | CheckMacValue 解說 + 12 語言實作 | 25 分鐘（非 PHP 必讀） |
| 14 | guides/14-aes-encryption.md | AES 加解密解說 + 12 語言實作 | 25 分鐘（非 PHP 必讀） |
| 19 | guides/19-http-protocol-reference.md | HTTP 協議參考（跨語言必讀） | 20 分鐘 |
| 20 | guides/20-error-codes-reference.md | 全服務錯誤碼集中參考 | 10 分鐘 |
| 21 | guides/21-webhook-events-reference.md | 統一 Callback/Webhook 參考 | 15 分鐘 |

**運維與上線**

| # | 檔案 | 主題 | 預估閱讀 |
|---|------|------|:-------:|
| 15 | guides/15-troubleshooting.md | 除錯指南 + 錯誤碼 + 常見陷阱 | 15 分鐘 |
| 16 | guides/16-go-live-checklist.md | 上線檢查清單 | 20 分鐘 |
| 22 | guides/22-performance-scaling.md | 效能與擴展性指引 | 15 分鐘 |
| 23 | guides/23-multi-language-integration.md | 多語言整合完整指南（Go/Java/C#/TypeScript 完整 E2E；Kotlin/Ruby/Swift/Rust 差異指南；C/C++ 最小骨架；Mobile App iOS/Android 指引） | 8-15 分鐘（用 Section Index） |
| 24 | guides/24-local-development.md | 本地開發環境設定（ngrok / Cloudflare Tunnel / localtunnel / RequestBin）— localhost 無法接收 Callback 的解決方案 | 10 分鐘 |

### 程式語言規範（guides/lang-standards/）

> 生成目標語言程式碼時，同時載入對應規範檔。每份 ~150-250 行，涵蓋命名慣例、型別定義、錯誤處理、HTTP 設定、Callback Handler、環境變數、單元測試。

| 語言 | 檔案 |
|------|------|
| Python | guides/lang-standards/python.md |
| Node.js | guides/lang-standards/nodejs.md |
| TypeScript | guides/lang-standards/typescript.md |
| Go | guides/lang-standards/go.md |
| Java | guides/lang-standards/java.md |
| C# | guides/lang-standards/csharp.md |
| Kotlin | guides/lang-standards/kotlin.md |
| Ruby | guides/lang-standards/ruby.md |
| Rust | guides/lang-standards/rust.md |
| Swift | guides/lang-standards/swift.md |
| C | guides/lang-standards/c.md |
| C++ | guides/lang-standards/cpp.md |

### 官方 API 文件索引（references/）

> 完整索引（20 檔案 × 443 個 URL × 對應 Guide 映射）見 [references/README.md](./references/README.md)。

references/ 包含 6 大類 API 文件：Payment（8 檔, 174 URLs）、Invoice（4 檔, 119 URLs）、Logistics（3 檔, 76 URLs）、Ecticket（3 檔, 57 URLs）、Receipt（1 檔, 12 URLs）、Cart（1 檔, 5 URLs）。每個檔案收錄官方 API 技術文件的章節 URL 索引，搭配 web_fetch 即時讀取最新規格。

### ⚠️ AI 必讀：API 規格即時查閱機制

**references/ 是即時 API 規格入口，不是靜態文件。**

references/ 的 20 個檔案包含 443 個 URL，每個 URL 連結至綠界 `developers.ecpay.com.tw` 官方最新 API 規格頁面。guides/ 提供整合知識（如何串接），references/ 提供即時規格來源（最新參數表、欄位定義）。**兩者結合才是完整的回答。**

#### 何時必須即時查閱 references/

當開發者詢問以下類型問題時，**禁止僅依賴 guides/ 內容回答**，必須從 references/ 取得對應 URL 並即時讀取：

- **生成 API 呼叫程式碼時**（確認端點路徑、必填參數、回應格式是否為最新）
- 具體 API 參數名稱、型態、必填/選填、長度限制
- 最新錯誤碼清單或特定錯誤碼含義
- API 端點是否有更新或異動
- 回應欄位的完整規格
- **確認該 API 的注意事項、限制條件、金額範圍、時間限制**（API 頁面的 ⚠ 注意事項段落包含不斷更新的業務規則）
- guides/ 內容與開發者實際呼叫結果有出入時

> ⚠️ **guides/ 中的所有參數表和端點 URL 標記為 SNAPSHOT（2026-03）**，僅供整合流程理解，不可直接作為程式碼生成依據。
> 生成程式碼時，**必須**以 references/ → web_fetch 取得的即時規格為準。

#### 即時查閱流程

```
需要 API 規格？（生成程式碼 / 問規格細節 / 翻譯範例）
├── 1. 從本索引或 guides/ 內的 references/ 連結，找到對應檔案
│      例：references/Payment/全方位金流API技術文件.md
├── 2. 讀取該檔案，找到相關章節的 URL
│      例：## 付款方式 / 信用卡一次付清 → https://developers.ecpay.com.tw/2866.md
├── 3. 使用 web_fetch 工具讀取該 URL（取得官方最新規格）
│      ├── 成功 → 進入步驟 3a
│      ├── 404 / 連線失敗 → 嘗試 web_fetch https://developers.ecpay.com.tw 首頁搜尋對應主題
│      │      └── 仍失敗 → 以 guides/ 內容備援，但必須告知開發者並附上 reference URL
│      └── 回傳內容缺少參數表 → 告知開發者建議手動開啟該 URL 確認
├── 3a. 摘取頁面中所有 ⚠ 注意事項段落，在回覆或程式碼註解中主動告知開發者
├── 3b. 首次串接？（本次對話中第一次涉及該服務）
│      └── 是 → web_fetch 該服務的「介接注意事項」頁面（見 §介接注意事項 URL 速查表）
│             摘取所有注意事項，告知開發者關鍵限制
├── 4. 結合 guides/ 的整合知識 + 即時規格 + 注意事項回答開發者
└── 5. 開發者問到 references/ 未收錄的 API？
       → ⚠️ 禁止從 AI 記憶中編造或猜測 URL
       → 可嘗試 web_fetch https://developers.ecpay.com.tw 首頁搜尋
       → 若找到 developers.ecpay.com.tw 下的頁面，可引用但須註明「此 URL 未收錄於 references/ 索引，請自行確認有效性」
       → 若找不到，告知��發者聯繫綠界客服 (02-2655-1775) 確認
       → 禁止引用非 ecpay.com.tw 網域的第三方連結作為 API 規格來源
```

#### 各 AI 平台即時讀取工具

| AI 平台 | 讀取 URL 的工具 | 用法 |
|---------|----------------|------|
| Claude Code | `web_fetch` | `web_fetch(url="https://developers.ecpay.com.tw/2866.md")` |
| VS Code Copilot Chat | `#file` + `@workspace` | 引用本地 guides/，搭配 `@workspace` 搜尋知識庫 |
| GitHub Copilot CLI | `web_fetch` / `fetch` | 同上 |
| OpenAI GPTs | Web Search / 瀏覽 | 啟用「Web Search」後直接瀏覽 URL |
| Cursor | `@web` / `fetch`（MCP） | 使用 `@web` 搜尋或透過 Fetch MCP 讀取 URL |

> ⚠️ **web_fetch 失敗時的備援**：若 web_fetch 逾時、回傳 404 或連線失敗：
> 1. 先嘗試 web_fetch `https://developers.ecpay.com.tw` 首頁，搜尋對應 API 主題的替代 URL
> 2. 仍失敗時，以 guides/ 內容作為備援回答，但**必須告知開發者**：「此規格來自 SNAPSHOT（{日期}），可能非最新，建議手動確認」
> 3. **必須附上**對應的 reference 檔案路徑和原始 URL，供開發者自行查閱或回報失效

> 💡 **guides/ 與 references/ 的分工**：
> - **guides/** = **如何做**（整合邏輯、流程、範例程式碼）— 靜態知識庫
> - **references/** = **最新規格 + 注意事項**（當前 API 參數定義、欄位規格、⚠ 限制條件）— 動態規格入口
> - guides/ 告訴你怎麼串，references/ 確保你串的參數是最新的，**且主動揭露官方頁面中的注意事項**。

### PHP 範例（scripts/SDK_PHP/example/）

> 共 134 個驗證過的 PHP 範例，涵蓋 Payment（44）、Invoice（42）、Logistics（48）。詳細目錄見 `scripts/SDK_PHP/example/`。

## 維護指引

> 維護者請參閱 [CONTRIBUTING.md](./CONTRIBUTING.md) §維護指引（定期驗證、URL 回退策略、SDK 更新流程）。

## 更新紀錄

> 目前版本 V3.0
