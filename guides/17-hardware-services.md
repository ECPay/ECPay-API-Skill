> 對應 ECPay API 版本 | 最後更新：2026-05

# 硬體與專用服務指引

> 本文件涵蓋兩種非標準 HTTP API 的特殊服務：**POS 刷卡機**（透過 RS-232 序列通訊連接綠界 EDC 刷卡機硬體）與**直播收款**（後台建立收款網址，僅需實作 Callback）。
>
> 需要標準線上金流 HTTP API → [guides/01 AIO](./01-payment-aio.md)、[guides/02 ECPG](./02-payment-ecpg.md)

## 目錄

- [POS 刷卡機串接指引](#pos-刷卡機串接指引)
- [直播收款指引](#直播收款指引)

---

## POS 刷卡機串接指引

> ⚠️ **SNAPSHOT 2026-05** | 來源：`references/Payment/刷卡機POS串接規格.md` — 生成程式碼前請 web_fetch 取得最新規格

> **本指南為初步整合指引**，提供 POS 串接的概念說明和官方文件索引。
> POS 刷卡機為硬體設備，需搭配特定通訊協議，詳細技術規格見
> `references/Payment/刷卡機POS串接規格.md`。
>
> **注意**：綠界 POS 串接的官方通訊規格為 **RS-232 序列通訊**（115200 Baud Rate，8N1，ASCII 電文 + LRC XOR 校驗），透過序列埠連接綠界出貨的 **EDC 刷卡機硬體**，**非 ECPay 線上 HTTP API**（無 REST 端點）。`scripts/SDK_PHP/example/` 目錄中無對應範例。請參照 `references/Payment/刷卡機POS串接規格.md` 的協議規格自行實作。

### 概述

POS 刷卡機串接適用於實體門市、餐飲業等需要現場刷卡收款的場景。與線上金流（AIO/ECPG）不同，POS 整合需要搭配綠界出貨的 **EDC 刷卡機硬體**，透過 RS-232 序列埠連接 POS 應用。

### 適用場景

- 實體門市收款（零售、百貨）
- 餐飲業桌邊結帳
- 市集、展覽臨時收款
- 需要感應支付（NFC）的場景

### 與線上金流的差異

| 面向 | 線上金流 (AIO/ECPG) | POS 刷卡機 |
|------|---------------------|-----------|
| 付款方式 | 消費者在網頁/App 操作 | 消費者在實體終端刷卡/感應 |
| 整合方式 | HTTP API（網路請求） | RS-232 序列通訊（連接 EDC 硬體） |
| 通訊規格 | JSON / Form POST over HTTPS | ASCII 電文 `STX + DATA + ETX + LRC` |
| 加密方式 | CheckMacValue / AES | 電文層級 SHA-1 雜湊（依規格） |
| 退款 | API 操作 | 透過 POS 應用送指令或綠界後台處理 |

### 串接架構

```
你的 POS 應用                 ECPay EDC 刷卡機（綠界出貨）       ECPay / 收單銀行
     │                                │                              │
     ├─ (RS-232) 授權請求 ──────────→ │                              │
     │                                ├─ 消費者刷卡 / 感應             │
     │                                ├─ 授權連線 ──────────────────→ │
     │                                │                              ├─ 銀行授權
     │                                │ ←── 授權回應 ──────────────── │
     │ ←─ (RS-232) 交易結果 ───────── │                              │
     │                                │                              │
     │   （營業結束）                   │                              │
     ├─ (RS-232) 結帳指令 ──────────→ │                              │
     │                                ├─ 批次上傳 ──────────────────→ │
     │ ←─ (RS-232) 結帳結果 ───────── │                              │
```

> ℹ️ 「你的 POS 應用」與「EDC 刷卡機」之間透過 **RS-232 序列埠**通訊（綠界官方規格）；EDC 刷卡機本身連線至綠界 / 收單銀行完成授權，**不需要也不存在從 POS 應用直接呼叫綠界 REST API 的路徑**。

### 通訊方式

綠界 EDC 刷卡機官方通訊規格為 **RS-232 序列通訊**（來源：`developers.ecpay.com.tw/32591.md`）：

| 項目 | 規格 |
|------|------|
| 介面 | RS-232 序列資料通訊介面 |
| 傳輸模式 | 非同步傳輸（Asynchronous） |
| Baud Rate | 115200 |
| 資料設定 | 8N1（8 data bits、No parity、1 stop bit） |
| 電文結構 | `STX + DATA(600 chars, ASCII) + ETX + LRC` |
| 校驗 | LRC：初始值 `0`，與 `DATA + ETX` 進行 XOR 位元運算 |
| 控制字元 | STX = `0x02`、ETX = `0x03`、ACK = `0x06`（確認收到請求） |

> ⚠️ 綠界 POS 通訊規格**不包含** TCP/IP、HTTP/HTTPS 等網路通訊方式；所有交易電文一律透過序列埠（RS-232）傳輸到 EDC 刷卡機。

### 與線上金流的協議差異

POS 刷卡機**不使用 HTTP API**，整合對象是 **RS-232 連接的 EDC 刷卡機硬體**（綠界出貨），刷卡機本身負責與銀行授權連線。

與線上金流（AIO CMV-SHA256 / ECPG AES-JSON）的網路 API 協議完全不同：POS 整合需以綠界 POS 通訊規格（ASCII 電文 + LRC 校驗）透過序列埠串接 EDC，無 REST API 端點可呼叫。

詳細規格請參考官方文件：`references/Payment/刷卡機POS串接規格.md`

> 若需要線上收款的 HTTP API 串接，請參考 [guides/19-http-protocol-reference.md](./19-http-protocol-reference.md)。

### API 端點概覽

POS 刷卡機的串接規格包含：

- 交易授權（一般交易 / 分期 / 紅利）
- 交易查詢
- 取消交易（void）
- 退款
- 結帳批次上傳
- 終端機參數下載

### 基本交易流程

#### 1. 授權請求

```
發送授權請求 → 等待回應（timeout 建議 60 秒）
├── 授權成功：記錄授權碼、交易序號
├── 授權失敗：顯示失敗原因
└── 逾時：發送查詢確認交易狀態
```

#### 2. 結帳批次上傳

每日營業結束後，需將當日所有交易上傳至綠界進行對帳：

```
1. 收集當日所有授權成功的交易
2. 組成批次上傳電文
3. 透過 RS-232 送出結帳批次上傳指令至 EDC 刷卡機
4. 確認上傳結果（逐筆比對）
```

### 常見整合注意事項

| 項目 | 說明 |
|------|------|
| 心跳機制 | 建議每 30 秒與 EDC 刷卡機保持心跳電文，確認序列埠連線狀態 |
| 斷線重連 | 序列埠連線中斷（線材鬆脫、刷卡機重啟等）後自動重連，交易中斷需查詢確認狀態 |
| 結帳批次 | 每日必須結帳，未結帳的交易隔日可能無法請款 |
| 簽單列印 | 授權成功後需列印簽單，供消費者簽名 |
| 交易逾時 | 授權請求建議 timeout 60 秒，逾時後查詢確認 |

### 完整規格文件

詳細的 POS 通訊協議規格、指令定義、錯誤碼對照,請參閱官方技術文件索引:

> 📄 `references/Payment/刷卡機POS串接規格.md`(13 個外部文件 URL;**實作前務必 web_fetch** 取得最新規格)

### POS 整合快速指引

| 步驟 | 說明 |
|:---:|------|
| 1 | 向綠界申請 POS 刷卡機（EDC）服務(需獨立申請,非金流帳號自動包含)|
| 2 | 由綠界出貨 EDC 刷卡機硬體,並取得 POS 通訊規格與相關驗證金鑰(與一般金流帳號的 HashKey/HashIV **不同**)|
| 3 | 驗證機制:**RS-232 電文 + SHA-1 雜湊**(資料格式說明定義),**與 AIO 金流的 CheckMacValue SHA256 不同**。正式實作前**必須 web_fetch `references/Payment/刷卡機POS串接規格.md` 中列出的官方 URL** 確認電文欄位、雜湊計算方式與服務別差異(信用卡銷售 / 紅利 / 分期 / 預先授權 / 結帳 等)|
| 4 | 通訊介面:**RS-232 序列埠**(115200 Baud Rate、8N1、ASCII 電文 + LRC XOR 校驗);**無 REST API 端點**,所有交易電文皆透過序列埠送至 EDC 刷卡機 |
| 5 | 測試:以綠界出貨的 EDC 刷卡機(測試模式)實機驗證,並於營業結束依規格送出結帳批次上傳指令對帳 |

> ℹ️ **POS 完整規格**：使用 AI 工具時，請 web_fetch `references/Payment/刷卡機POS串接規格.md` 中的 URL 取得最新電文規格、欄位定義與驗證機制。

### 相關文件

- 線上信用卡收款：[guides/01-payment-aio.md](./01-payment-aio.md)
- 嵌入式付款：[guides/02-payment-ecpg.md](./02-payment-ecpg.md)
- 除錯指南：[guides/15-troubleshooting.md](./15-troubleshooting.md)
- 上線檢查：[guides/16-go-live-checklist.md](./16-go-live-checklist.md)

---

## 直播收款指引

> ⚠️ **SNAPSHOT 2026-03** | 來源：`references/Payment/直播主收款網址串接技術文件.md` — 生成程式碼前請 web_fetch 取得最新規格

> **本指南為初步整合指引**，說明直播收款的概念與 ReturnURL Callback 處理方式。
> 收款網址透過綠界後台建立（無建立 API），特店僅需實作付款成功 Callback。
> 詳細規格見 `references/Payment/直播主收款網址串接技術文件.md`。
>
> **注意**：本指南的 PHP 範例為依 `references/Payment/直播主收款網址串接技術文件.md` 手寫，非官方 SDK 範例。

### 概述

直播收款網址服務讓直播主或賣家能快速產生收款連結，在直播過程中分享給觀眾完成付款。適用於直播電商、網紅經濟等即時銷售場景。

### 適用場景

- 直播電商（Facebook Live、YouTube Live、蝦皮直播等）
- 網紅 / KOL 即時帶貨
- 社群團購分享收款連結
- 不需要自建購物車的輕量收款

### 核心流程

```
1. 賣家透過綠界後台建立收款網址（收款工具 → 實況主收款功能）
2. 直播中分享收款連結給觀眾
3. 觀眾點擊連結完成付款
4. 綠界以 JSON POST 通知 ReturnURL（付款成功 Callback）
5. 賣家透過後台查詢訂單與管理收款網址
```

### HTTP 協議速查（ReturnURL Callback）

| 項目 | 規格 |
|------|------|
| 協議模式 | AES-JSON + CMV（ECTicket 式）— 詳見 [guides/19-http-protocol-reference.md](./19-http-protocol-reference.md) |
| Callback 格式 | JSON POST (`application/json`) |
| 認證 | **AES-128-CBC**(Block Mode,PKCS7 padding)加密 Data 欄位;Key=HashKey(16 bytes ASCII)、IV=HashIV(16 bytes ASCII)— 詳見 [guides/14-aes-encryption.md](./14-aes-encryption.md) |
| 回應結構 | 三層 JSON(TransCode → 解密 Data → RtnCode) |
| CMV 驗證 | ECTicket 式 CheckMacValue(SHA256);與ECTicket相同公式(詳見 [guides/09 §CheckMacValue 計算](./09-ecticket.md))。⚠️ **直播收款 CMV 公式尚未完整公開**,實作前**必須 web_fetch `references/Payment/直播主收款網址串接技術文件.md` 中列出的官方 URL** 確認最新規格。驗證使用 timing-safe 比對(`hash_equals` / `crypto.timingSafeEqual` / `hmac.compare_digest`) |
| 回應 | 純文字 `1\|OK` |

### 建立收款網址（後台操作）

> ⚠️ 直播收款的收款網址透過綠界後台設定（收款工具 → 實況主收款功能），**無建立訂單 API**。特店僅需實作 ReturnURL（付款成功通知 Callback）。

> ℹ️ 查詢、關閉、紀錄等功能透過綠界後台操作，目前無對應 API。

測試帳號使用 ECPG 同組（MerchantID `3002607` / HashKey `pwFHCqoQZGmho4w6` / HashIV `EkRm7iFT261dpevs`）。

### ReturnURL 付款成功通知參數

> ⚠️ **SNAPSHOT 2026-03** | 來源：`references/Payment/直播主收款網址串接技術文件.md`

消費者付款完成後，綠界以 JSON POST 傳送付款結果至 ReturnURL。僅通知付款成功，付款失敗或待付款不通知。

#### 外層 Response（JSON）

| 參數 | 型別 | 說明 |
|------|------|------|
| MerchantID | String(10) | 特店編號 |
| RpHeader | JSON | 回傳資料（含 Timestamp） |
| TransCode | Int | 回傳代碼（`1` = 傳輸成功，需再檢查 Data 內的 RtnCode） |
| TransMsg | String(200) | 回傳訊息 |
| Data | String | 加密資料（AES 解密後為 JSON） |
| CheckMacValue | String | 檢查碼（ECTicket 式 CMV，需 timing-safe 驗證） |

#### Data 解密後參數（JSON）

| 參數 | 型別 | 說明 |
|------|------|------|
| RtnCode | Int | 交易狀態（`1` = 成功） |
| RtnMsg | String(200) | 回應訊息 |
| MerchantID | String(10) | 特店編號 |
| DonateURL | String(200) | 贊助收款網址 |
| SimulatePaid | Int | 是否為模擬付款（`0` = 非模擬，`1` = 模擬付款，勿出貨） |
| OrderInfo | JSON | 訂單資訊（見下方） |
| PatronName | String(100) | 贊助者名稱 |
| PatronNote | String(100) | 贊助者留言 |
| LivestreamURL | String(200) | 直播頻道網址（未設定時回傳空字串） |

#### OrderInfo 子物件

| 參數 | 型別 | 說明 |
|------|------|------|
| MerchantTradeNo | String(20) | 特店交易編號 |
| TradeNo | String(20) | 綠界交易編號（請保存與 MerchantTradeNo 的關聯） |
| TradeAmt | Int | 交易金額 |
| TradeDate | String(20) | 訂單成立時間（yyyy/MM/dd HH:mm:ss） |
| PaymentType | String(20) | 付款方式（參考附錄回覆付款方式一覽表） |
| PaymentDate | String(20) | 付款時間（yyyy/MM/dd HH:mm:ss） |
| ChargeFee | Number | 手續費 |
| TradeStatus | String(8) | 交易狀態（`0` = 未付款，`1` = 已付款） |

> ⚠️ **注意事項**：
> - 當 `SimulatePaid` 為 `1` 時，為廠商後台模擬付款測試，綠界不會撥款，**請勿出貨**。
> - 特店務必判斷 `RtnCode` 是否為 `1`，非 `1` 時請勿出貨。
> - ATM/超商條碼/超商代碼的付款時間以銀行與超商告知綠界的銷帳時間為主。
> - 若未正確回應 `1|OK`，系統會隔 5~15 分鐘後重發，當天最多重複 4 次。

### 收款網址管理（後台操作）

- **有效期限**：建立時於後台設定，過期後消費者無法付款
- **狀態管理**：透過綠界後台關閉不再需要的收款網址（無對應 API）
- **付款通知**：消費者付款後，綠界以 **JSON POST** 傳送到 ReturnURL（格式同 ECTicket：AES 解密 Data + ECTicket-式 CMV，**非** AIO Form POST 格式）；驗證順序：TransCode === 1 → AES 解密 → CMV timing-safe 驗證 → RtnCode === 1（**整數**）；**回應純文字 `1|OK`**（⚠️ 與ECTicket不同 — ECTicket 回 AES+CMV，直播收款回純文字）

### 完整規格文件

詳細的 API 參數和串接流程，請參閱官方技術文件：

> 📄 `references/Payment/直播主收款網址串接技術文件.md`（7 個外部文件 URL）

### 直播收款快速指引

| 步驟 | 說明 |
|:---:|------|
| 1 | 向綠界申請「直播收款」功能（需獨立申請）|
| 2 | 使用 **AES-JSON + CheckMacValue（ECTicket 式 SHA256）**雙重驗證（與ECTicket相同協議）|
| 3 | 外層 JSON 需包含 `CheckMacValue`（公式同 ECTicket，非 AIO）|
| 4 | **ECPay 通知格式**（你收到的）：JSON POST，Data 欄位為 AES 加密 JSON，外層含 ECTicket 式 CheckMacValue（與ECTicket相同協議）。**你必須回應**：純文字 `1\|OK`（⚠️ 與ECTicket不同——ECTicket回應需 AES 加密 JSON + CheckMacValue）|
| 5 | API 端點：`https://ecpayment.ecpay.com.tw/`（測試環境：`https://ecpayment-stage.ecpay.com.tw/`）（詳見 `references/Payment/直播主收款網址串接技術文件.md`）|

> ⚠️ **直播收款的協議混淆**：雖然直播收款的 Callback 使用 ECTicket 式 CheckMacValue，但回應格式為 `1|OK`（不同於ECTicket的 AES JSON 回應）。
>
> ℹ️ **完整規格**：請 web_fetch `references/Payment/直播主收款網址串接技術文件.md` 中的 URL 取得最新參數表。

### 相關文件

- 標準金流串接：[guides/01-payment-aio.md](./01-payment-aio.md)
- 除錯指南：[guides/15-troubleshooting.md](./15-troubleshooting.md)
- 上線檢查：[guides/16-go-live-checklist.md](./16-go-live-checklist.md)
