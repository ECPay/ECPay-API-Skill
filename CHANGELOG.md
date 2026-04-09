# Changelog

所有重要的版本變更都記錄在此。格式遵循 [Keep a Changelog](https://keepachangelog.com/zh-TW/1.0.0/)。

---

## [Unreleased]

### 修正

- **README.md Windsurf 安裝段落完整重寫**（Line 112, 186–195）：原寫法 `git clone ... .windsurf/skills/ecpay` 與 `~/.codeium/windsurf/skills/ecpay` 皆非 Windsurf 官方支援路徑——`docs.windsurf.com` 明確說明 Windsurf 沒有 skills 目錄機制，官方規則系統為 `.windsurf/rules/*.md`（需 `trigger:` frontmatter）或 `AGENTS.md`（Windsurf 原生支援自動偵測）。依 Cursor 段落模式重寫為：Clone 至 `.ecpay-skill/` → 建立 `AGENTS.md` 或 `.windsurf/rules/ecpay.md` 引用。原錯誤路徑會導致 Cascade 完全不載入 ECPay Skill，使用者會誤以為已安裝
- **README.md:227 & CONTRIBUTING.md:161 版本固定範例 `git checkout v1.5`**：v1.5 tag 從未建立，執行會報 `error: pathspec 'v1.5' did not match any file(s) known to git`。統一改寫為「先 `git tag -l` 查詢可用 tag，再 checkout 實際 tag」的通用範例，並明列目前可用 tag `v1.0` / `v2.5` / `v2.6`
- **Git tags 補建**：本地建立 annotated tag `v2.5`（指向 `5bd6159 ECPay API Skill V2.5`）與 `v2.6`（指向 `8d50623 ECPay API Skill V2.6`），對應已發布的 V2.5 / V2.6 commit。需 `git push origin --tags` 同步至 remote 後，使用者才能透過 `git checkout v2.6` 固定版本（見 CONTRIBUTING.md §版本發布流程 的 tag push 警告）

---

## [1.5.6] — 2026-04-10 (V2.6)

### 修正

- **README.md:33 Claude Code 官方文件 URL**：`docs.anthropic.com/en/docs/claude-code/overview` → `code.claude.com/docs/en/overview`，原 URL 已 301 永久重定向至新網域；同時補充「或 Anthropic Console API 帳號」，與 Claude Code 官方 overview「a Claude subscription or Anthropic Console account」一致
- **README.md:39 & SETUP.md:80 OpenClaw Node 版本需求**：原 README 寫「Node ≥ 22」涵蓋到 22.0–22.13 的不支援區間；原 SETUP 寫「Node ≥ 22.16」版本號錯誤。統一修正為 docs.openclaw.ai 官方原文「Node 22.14+（LTS）或 Node 24（推薦）」
- **README.md:40 ChatGPT GPTs 方案名稱**：Team 方案已於 2025-08-29 由 OpenAI 正式更名為 Business（chatgpt.com/pricing 現行方案為 Free / Go / Plus / Pro / Business / Enterprise），表格方案清單由 `Plus / Pro / Team / Enterprise / Edu` 修正為 `Plus / Pro / Business / Enterprise / Edu`
- **../CLAUDE.md（父層 skills repo）版本描述**：`ecpay-skill` 項目從 V2.3 同步至 V2.6，修正長期未同步的 doc drift

### 版本同步

- SKILL.md front-matter / SKILL_OPENAI.md / README.md / SETUP.md / AGENTS.md / GEMINI.md / google_AI_studio.md / vscode_copilot.md / visual_studio_2026.md / .github/copilot-instructions.md / 業務說明.md / 父層 CLAUDE.md 全數同步至 **V2.6**

---

## [1.5.5] — 2026-04-09 (V2.5)

### 修正

- **guides/lang-standards/{go,cpp,typescript}.md RqHeader.Revision 註解重寫**:原誤將「發票 B2C/B2B」合併為 `3.0.0`、站內付 2.0 誤寫為 `1.0.0`。正確為 B2C=`3.0.0`、B2B=`1.0.0`(+RqID);站內付 2.0/幕後授權/幕後取號/電子票證/直播收款**不使用** Revision
- **guides/14 §使用場景 電子票證 Revision**:由 `1.0.0` 改為**不使用**,與 guides/09:220 及 guides/20:138「ECTicket RqHeader 僅需 Timestamp」統一
- **guides/20:137 全方位物流 Revision**:補全為 `Timestamp + Revision: "1.0.0"`,並合併跨境物流同列
- **guides/05 B2B 發票 ItemTax vs ItemTaxType 警告強化**:新增三層警告框、功能對照表補充稅額欄位橫列、Issue.php:31 SDK 已知 bug 獨立標註
- **guides/04 新增 InvoiceNo vs InvoiceNumber 跨 B2C/B2B 欄位名差異警告**
- **guides/04 & guides/19 SalesAmount 描述補充 `vat='0'/'1'` 依賴邏輯**:B2C/離線發票 SalesAmount、ItemPrice、ItemAmount 的含稅/未稅性隨 vat 參數變化
- **guides/17 POS 刷卡機 SHA-1 聲稱與直播收款 CMV 公式**:改為「廠商規格為準,實作前必須 web_fetch references 驗證」,避免誤導
- **guides/20:787 & guides/22:42, 202 直播收款 Callback 回應格式措辭統一**:三處措辭改為一致表述
- **guides/04 & guides/05 ZeroTaxRateReason 移除「115 年 1 月 1 日生效」時效語氣**:該日期已過,改為強制要求
- **guides/06:62 國內物流 MerchantTradeDate 日期格式**:補充 PHP `date('Y/m/d H:i:s')` 不補前導零陷阱
- **guides/07 全方位物流 HTTP 協議速查**:新增 Timestamp 驗證期限(**5 分鐘**,與跨境物流/ECPG 10 分鐘差異)
- **guides/20 協議總覽表**:新增「AIO 對帳檔下載 vendor.ecpay.com.tw」專用域名列
- **guides/19 離線發票測試帳號 `3085340` 獨立警示**:列出不可用的其他帳號 2000132/3002607
- **guides/09 純發行模式端點表前置差異提示**:`/api/Ticket/` vs 價金保管 `/api/issuance/` 路徑區別
- **guides/10 購物車 WooCommerce/Magento/Shopify 說明補充**:WooCommerce 錯誤配置症狀;Magento 版本建議 2.4.5;Shopify 補充 web_fetch 提示;Composer 套件名來源加上 Packagist
- **guides/17 直播收款 HTTP 速查 AES 模式標註**:補標「AES-128-CBC Block Mode + PKCS7 padding」、Key/IV 來源
- **guides/04:72 B2C Revision 欄位位置註明**:在 `RqHeader.Revision`(不在 `Data` 內)
- **guides/05:345 B2B ItemAmount 四捨五入規則補齊**:總和誤差 ≤ 1 元

### 版本同步

- SKILL.md front-matter / AGENTS.md / GEMINI.md / google_AI_studio.md / vscode_copilot.md / visual_studio_2026.md / SETUP.md / SKILL_OPENAI.md / README.md / 業務說明.md / .github/copilot-instructions.md 全數同步至 **V2.5**

---

## [1.5.4] — 2026-03-21

### 新增

- **guides/25 本地開發隧道指南**：全新 219 行指南，涵蓋 ngrok（安裝 + 隧道 + 限制）、Cloudflare Tunnel（固定 URL、免費）、localtunnel（零安裝）、RequestBin（僅供檢閱）、Callback 確認步驟與 3 條 FAQ（URL 過期、驗證、Docker）
- **guides/09 IssueType 對應必填欄位速查表**：新增 `§ IssueType 對應必填欄位速查` 4 行對照表（IssueType 1–4），一眼看清各票種的 TicketInfo 必填欄位組合
- **guides/16 整合驗收清單**：上線前端到端驗收的 5 個子清單（AIO、ECPG、發票、物流、通用），20 條驗收標準，補齊原缺少的成功定義
- **guides/22 Callback 冪等性實作範例**：新增 PHP+MySQL（ON DUPLICATE KEY UPDATE）、Node.js+PostgreSQL（ON CONFLICT DO UPDATE + 已付款狀態守衛）、Python+SQLAlchemy（pg_insert on_conflict_do_update）三份可直接使用的實作片段
- **guides/lang-standards/swift.md 完整補充**：新增 `aesUrlEncode` CharacterSet 完整實作、AES-JSON 三層結構型別定義（EcpayAesResponse / EcpayInnerResponse / GetTokenResponse structs）、Vapor Callback Handler（JSON POST + 整數 RtnCode）
- **guides/lang-standards/rust.md 完整補充**：新增 `aes_url_encode`（percent_encoding crate + 自訂 AsciiSet）、Axum Callback Handler（Json extractor + tracing::error! + 整數 RtnCode）
- **test-vectors 新增兩條測試向量**：`alphabetic-key-order-go-java`（說明 Go/Java HashMap key 排序差異）、`pkcs7-exact-block-boundary`（PKCS7 整塊邊界說明），補齊跨語言最常踩坑的驗證案例
- **guides/24 決策導航表**：在語言快速導航前新增 `## 何時需要本指南？` 協定選擇速查表，幫助開發者依協定模式找到正確章節

### 修正

- **guides/02a ECPG 端點速查表（Blocker B-1）**：在 Token 生命週期圖與 5 步驟流程間插入 `⚡ API 端點速查（測試環境）` 表，明確區分 `ecpg-stage` 與 `ecpayment-stage` 各端點歸屬，消除 404 最常見根因
- **guides/03 AES-JSON 雙層錯誤檢查說明（High H-5）**：在概述後插入 `⚠️ AES-JSON 開發者必讀：雙層錯誤檢查` 警告框，說明 TransCode（外層）→ RtnCode（內層）兩步驗證邏輯，對齊 guides/07 模式
- **guides/07 RqHeader.Revision 必填說明（Medium M-7）**：從模糊的「依 SDK 慣例」改為 `**必填 "1.0.0"（固定值）**`，並補充未填後果（TransCode ≠ 1）
- **guides/09 CMV 公式交叉引用（Medium M-6）**：將 `見 guides/13` 改為自引 `§CheckMacValue 計算`，並明確警告 AIO CMV 公式與 ECTicket CMV 不相容，防止錯誤套用
- **guides/14 Go/Java JSON 序列化警告（High H-4）**：在 Go 與 Java 程式碼區塊前各插入顯眼 `⚠️ 序列化必查清單` 三項核查，涵蓋 struct tag / key 順序 / 整數型別等常見靜默失敗
- **guides/15 5 分鐘根因診斷流程（Opt. O-7）**：在 §2（ReturnURL）與 §27（AIO ReturnURL）新增逐步診斷樹，含 PHP/Node.js 日誌片段與 4 條 URL 設定根因，縮短除錯時間
- **guides/16 上線障礙後果說明（Opt. O-10）**：5 條紅燈項目各補充 `若未切換` 後果句（如：API 打到測試環境，不產生真實資金流動），強化清單的執行意義
- **guides/24 語言快速導航行號校正（Opt. O-6）**：全部 10 個語言區段（Go/Java/C#/Kotlin/Ruby/Swift/Rust/Mobile/C/C++）的行號與 AI Section Index 完全同步（校正日期：2026-03-21）
- **SKILL.md 新使用者引導（Opt. O-1）**：在工作流程標題下方新增 `📖 首次使用？從 guides/00 開始` 引導區塊
- **SKILL.md Callback 格式速查表（High H-2）**：在語言陷阱速查表前新增 6 行 Callback 格式對照表（服務、URL 類型、格式、特殊欄位），補齊最常導致靜默失敗的格式差異
- **SKILL.md 文件索引更新（Blocker B-4）**：guides/24 說明更新為 `Kotlin/Ruby/Swift/Rust 差異指南；C/C++ 最小骨架`；新增 guides/25 本地開發條目
- **guides/17 POS 與直播收款快速指引（Opt. O-13）**：新增 POS 整合 5 步驟表（CMV-SHA256 協定標注）與直播收款 5 步驟表（AES-JSON+CMV 協定，含 `1|OK` 回應格式說明）

### 工具

- **test-vectors/aes-encryption.json 補充邊界案例**：`alphabetic-key-order-go-java` + `pkcs7-exact-block-boundary` 兩條測試向量，供多語言實作交叉驗證

### 文件

- **commands/ 6 個指令檔新增意圖確認與下一步導航（Opt. O-11）**：ecpay-pay / ecpay-debug / ecpay-invoice / ecpay-logistics / ecpay-ecticket / ecpay-go-live 各自補入頂部意圖確認區塊（4 條條件分支）與底部跨指令導航連結
- **CONTRIBUTING.md AI Section Index 維護規則（Opt. O-12）**：新增 `## AI Section Index 維護規則` 小節，說明何時更新、bash 驗證方法、HTML comment 格式與變更類型→需更新檔案對照表
- **版本號升級**：V1.5.3 → V1.5.4（全部 8 個入口文件同步：SKILL.md、SKILL_OPENAI.md、README.md、SETUP.md、AGENTS.md、GEMINI.md、copilot-instructions.md、CHANGELOG.md）

---

## [1.5.3] — 2026-03-21

### 新增

- **guides/11 場景一端到端 Mermaid 序列圖**：以 `sequenceDiagram` 呈現消費者→你的伺服器→ECPay 金流/發票/物流的完整互動時序，RtnCode 型別標注內嵌於圖中
- **guides/22 各服務 Callback 重試規則對照表**：獨立表格列出 AIO/站內付 2.0/幕後授權/物流/電子票證/直播收款各自的重試間隔、最大次數、觸發條件與重試停止後應對策略
- **guides/23 如何測定你的基線**：新增「基線建立步驟」4 步指引（選取穩定期→計算關鍵指標→設定警示門檻→記錄促銷期行為），補充「若無歷史資料（新服務）」的初始門檻建議
- **guides/08 AES-JSON 雙層錯誤檢查區塊**：跨境物流原缺少此必讀警告，補入完整 `⚠️ AES-JSON 開發者必讀：雙層錯誤檢查` 區塊（含 RtnCode **整數**型別標注）
- **guides/00 lang-standards/ 快速索引**：新增 12 種語言的直連連結表（nodejs/python/typescript/go/java/csharp/kotlin/ruby/swift/rust/c/cpp），說明每份檔案涵蓋的內容
- **guides/02a-c 延伸閱讀導航**：三個 ECPG 子指南各自新增「延伸閱讀」底部導航表，清楚標示本文位置並連結所有兄弟指南
- **references/README.md 分段標題與快速跳轉**：原單一大表拆分為 5 個 `###` 小節（金流/發票/物流/電子票證/購物車），頂部加入快速跳轉鏈路

### 修正

- **AGENTS.md / GEMINI.md 補入 Rule 30（分帳付款不支援）**：與 SKILL_OPENAI.md Rule 27 對齊；新增最近一次 parity 驗證記錄（2026-03-21）
- **guides/00 新手最常踩的坑補入第 6 坑（ECPG 雙 Domain 混用）**：症狀為查詢/退款呼叫回 404，解法明確區分 `ecpg(-stage)` 與 `ecpayment(-stage)` 的用途
- **README.md 測試帳號表補入 HashKey / HashIV**：原表僅列 MerchantID，補齊 HashKey、HashIV、協定欄位，並加入金流/物流/發票不可混用警告
- **guides/04/07/08/09 RtnCode 整數型別標注**：AES-JSON 雙層檢查說明中，`RtnCode === 1` 補充 `（**整數** 1，非字串 '1'）`，消除型別混淆靜默失敗
- **guides/20 §2.4 頂部 URLEncode 差異提示**：在 AES-JSON+CMV 小節最上方加入顯眼 ⚠️ 告示，提醒電子票證 URLEncode 與 AIO `ecpayUrlEncode` 不同

### 工具

- **scripts/validate-ai-index.sh 輸出增強**：通過項由靜默改為輸出 `✓  Label → "heading text"`，協助確認行號對應正確標題；失敗項加入 `Hint: Update AI Section Index line number` 提示

### 文件

- **版本號升級**：V1.5.2 → V1.5.3（全部 9 個入口文件同步：SKILL.md、SKILL_OPENAI.md、README.md、SETUP.md、AGENTS.md、GEMINI.md、copilot-instructions.md、CLAUDE.md、CHANGELOG.md）

---

## [1.5.2] — 2026-03-21

### 新增

- **guides/24 C/C++ AES-JSON B2C 發票開立最小骨架**：新增 C + libcurl + cJSON 的 AES-JSON 最小 POST 骨架（B2C 發票開立），涵蓋 6 步驟（組裝內層 JSON → AES 加密 → 組裝外層 JSON → libcurl POST → 雙層錯誤檢查 → AES 解密回應），補齊 C/C++ 為唯一缺 AES-JSON 範例的語言；更新語言導航表標記 `✅ minimal`，更新 AI Section Index 行號

### 修正（企業級多維審查）

- **guides/13 電子票證 CMV 公式措辭統一**：將 `strtoupper(SHA256(toLowerCase(urlencode(...))))` 改為 `strtoupper(SHA256(URLEncode(...)))` 並明確說明 `URLEncode = urlencode() 後接 strtolower()，不做 .NET 字元替換`，與 guides/20 line 324 完全一致；修正原描述「`toLowerCase` 僅作用於 URL 編碼的輸入字串」的錯誤（實際作用於 urlencode 輸出結果）
- **guides/14 AES URL Encode `%7E` 說明補充**：對照表「`~` 處理」欄位補充「AES URL Encode 不做 strtolower，故 hex 保持大寫 `%7E`（所有語言實作均統一如此）」，消除文件與程式碼之間的潛在歧義
- **guides/05 B2B RqID UUID v4 格式規範**：RqID 補充 UUID v4 格式詳細說明（`xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx`、含連字符）及 Python/Node.js/Java/C#/Go 各語言產生方式
- **guides/22 Callback 安全處理清單強化**：補充「何時需要佇列」決策矩陣（日交易 < 1,000 不需要 / > 1,000 建議 / 高並發必須），並重構清單為六步驟（驗簽→型別→業務狀態→冪等→立即回應→非同步後處理）

### 文件

- **版本號升級**：V1.5.1 → V1.5.2（全部 9 個入口文件同步：SKILL.md、SKILL_OPENAI.md、README.md、SETUP.md、AGENTS.md、GEMINI.md、copilot-instructions.md、CLAUDE.md、CHANGELOG.md）
- **GUIDE_INVENTORY_MARKDOWN.md 刪除**：純冗餘清單（手動生成，內容已由 SKILL.md 文件索引涵蓋），消除維護負債
- **CHANGELOG.md V1.5.1 連結補齊**：新增 `[1.5.1]` diff URL，更新 `[Unreleased]` 指向 `v1.5.1...HEAD`
- **copilot-instructions.md 版本說明更新**：移除「two-segment pattern」舊說法，改為「canonical version pattern from SKILL.md front-matter」，反映版本格式可為任意段數

---

## [1.5.1] — 2026-03-21

### 新增

- **guides/14 Swift `aesUrlEncode` 獨立函式**：新增 standalone `func aesUrlEncode(_ str: String) -> String`，與內嵌於 `aesEncrypt` 的邏輯並存，提升可測試性
- **guides/14 Kotlin `aesUrlEncode` 獨立函式**：新增 standalone `fun aesUrlEncode(source: String): String`，與 `ecpayAesEncrypt` 並存，提升可測試性
- **guides/00 ReturnURL 公開 URL 警告**：在 AIO 快速清單前新增 ⚠️ 警告框，提醒 localhost/127.0.0.1 無效，並提供 ngrok 啟動指令與 SimulatePaid=1 替代方案
- **guides/01 QueryTradeInfo TimeStamp 3 分鐘警告**：在查詢訂單程式碼前新增 ⚠️ 提示，強調 TimeStamp 有效期為 3 分鐘（非 10 分鐘），需每次呼叫前重新取得

### 修正

- **guides/14 AI Section Index 行號更新**：Swift/Kotlin/Ruby/測試向量/常見錯誤各區段行號，反映新增獨立函式後的正確位置
- **test-vectors/verify.py Windows Unicode 修正**：新增 `# -*- coding: utf-8 -*-` 宣告與 `sys.stdout.reconfigure(encoding='utf-8')`，修復 Windows cp950 終端機 ✓/✗ 符號亂碼

### 文件

- **guides/02b SNAPSHOT 時間戳**：新增缺失的 SNAPSHOT 標記，標明與 guides/02 主指南的版本對應關係
- **guides/02c SNAPSHOT 時間戳**：新增缺失的 SNAPSHOT 標記
- **guides/15 SNAPSHOT 時間戳**：新增缺失的 SNAPSHOT 標記，標明排查流程基於此版本 API 規格
- **SKILL_OPENAI.md 知識檔案說明**：更新必上傳清單說明，補充 guides/02a、guides/02b、guides/02c 的條件上傳說明
- **copilot-instructions.md 版本同步清單**：補充 CONTRIBUTING.md 英文摘要章節的版本同步項目（原清單遺漏）

### 移除

- **GUIDE_INVENTORY.txt / GUIDE_INVENTORY_QUICK_REFERENCE.txt 刪除**：純人工索引備忘稿，內容已由 SKILL.md 文件索引表涵蓋，屬多餘檔案

---

## [1.5.0] — 2026-03-20

### 修正

- **版本號統一**：V1.1 → V1.5（SKILL.md, SKILL_OPENAI.md, README.md, SETUP.md, AGENTS.md, GEMINI.md, CONTRIBUTING.md, copilot-instructions.md, CLAUDE.md）
- **指南數量修正**："25 份" → "28 份"（SKILL.md, SKILL_OPENAI.md, AGENTS.md, GEMINI.md, CLAUDE.md, copilot-instructions.md, CONTRIBUTING.md 英文摘要, GUIDE_INVENTORY_MARKDOWN.md）
- **ChatGPT GPTs 安裝指引修正**：`SKILL_OPENAI.md` 超過 8,000 字元無法貼入 Instructions，改為上傳至 Knowledge 欄位（README.md, SETUP.md, SKILL.md, copilot-instructions.md）
- SETUP.md ChatGPT 步驟重構：合併 Instructions 與 Knowledge Files 步驟，SKILL_OPENAI.md 列為 Knowledge #1

### 新增

- **SKILL.md 語言陷阱速查表**：11 種語言的最常見 Bug + 解決方案 + 對應 guide 位置
- **SKILL.md URL Encode 對照表**：`ecpayUrlEncode` vs `aesUrlEncode` 差異與混用後果
- **SKILL.md 快查表新增 guides/12**：PHP 開發者 SDK 參考連結
- **guides/22 RtnCode 型別警告**：Callback 參考頂部新增 CMV（字串）vs AES-JSON（整數）對照表
- **guides/15 帳號混用檢查（Step 0）**：CheckMacValue 排查流程最頂端加入帳號混用驗證
- **guides/02 新手提示**：Domain 警告後新增 guides/02a 快速路徑導引
- **AGENTS.md / GEMINI.md 同步註記**：標明核心內容同步自 SKILL.md
- **guides/02 CreatePayment 回應型別定義**：新增 TypeScript interface 作為跨語言巢狀結構參考
- **guides/00 Tier 0 體驗改善**：按鈕前新增錯誤預期警告，降低新手負面第一印象
- **guides/20 直播收款端點修正**：「見官方文件」→「後台操作，無 API」（正確反映後台功能無 API 端點）

### 修正（企業級審查）

- **guides/24 Go `aesURLEncode` 補全**：補齊 `!'()*` 5 個字元替換，與 PHP `urlencode` 行為一致
- **guides/14 Java/Kotlin `aesUrlEncode` 補 `!`**：`URLEncoder.encode` 不編碼 `!`，補 `.replace("!", "%21")`
- **guides/13 ECTicket CMV 公式修正**：補齊 `strtoupper()` 外層，統一與 guides/09、guides/20 的公式描述
- **guides/13 C 語言 Windows 相容**：新增 `_stricmp` 條件編譯，支援 MSVC 環境
- **guides/04 交叉引用修正**：guides/19 連結檔名 `19-offline-pos-invoice.md` → `19-invoice-offline.md`
- **SKILL.md 語言陷阱表擴充**：Java/C#/Go/Kotlin 補充 AES URL encode 陷阱（原僅列 CMV 陷阱）
- **SKILL_OPENAI.md 必上傳數量修正**：12 → 14 個，與 SETUP.md 一致（含 SKILL_OPENAI.md 自身和 guides/02a）
- **SKILL_OPENAI.md 規則合併**：Rule 23 和 28 重疊內容合併為單一 Callback 冪等性規則（28 條 → 27 條）
- **validate-version-sync.sh 擴充**：新增 copilot-instructions.md 和 CONTRIBUTING.md 英文摘要的檢查
- **CLAUDE.md guides/24 行數修正**：~992 → ~1310（反映 V1.1 新增 Java/C# E2E 後的實際行數）

### 移除

- **4 個重定向 stub 刪除**：CODEX_SETUP.md、GEMINI_SETUP.md、OPENCLAW_SETUP.md、OPENAI_SETUP.md（V1.1 整併至 SETUP.md 後已無用途）
- **簡報檔案移出主目錄**：移至 `docs/internal/`（不屬於 AI Skill 知識內容）

---

## [1.1.0] — 2026-03-20

### 結構性改善

- **guides/02 拆分**：3,738 行站內付 2.0 指南拆為 hub（~1,255 行）+ 3 個子指南
  - `02a-ecpg-quickstart.md` — 首次串接快速路徑 + Python/Node.js 完整範例
  - `02b-ecpg-atm-cvs-spa.md` — ATM/CVS 快速路徑 + SPA/React/Vue 整合
  - `02c-ecpg-app-production.md` — App 整合（iOS/Android）+ Apple Pay + 正式環境
  - hub 保留 Domain 警告、概述、付款流程、綁卡/退款/查詢/對帳、安全，並以 blockquote 重定向指向子指南
- **guides/16 上線清單分級**：新增「紅燈檢查（5 項必過）」優先區塊，其餘標記為「黃燈檢查」
- **SETUP 檔整併**：4 個平台安裝指南（CODEX_SETUP / GEMINI_SETUP / OPENCLAW_SETUP / OPENAI_SETUP）整併為統一 `SETUP.md`（~200 行），舊重定向 stub 已於 V1.5 移除

### 新增

- **Java 完整 E2E**：CMV-SHA256 AIO 信用卡付款 Web Server（JDK 11+，零外部依賴）— guides/24
- **C# 完整 E2E**：CMV-SHA256 AIO 信用卡付款 ASP.NET Core Minimal API（.NET 6+）— guides/24
- Java/C# 從差異指南升級為「E2E + AES 差異指南」

### 變更

- 版本號 V1.0 → V1.1（SKILL.md, SKILL_OPENAI.md, README.md, SETUP.md, AGENTS.md, GEMINI.md）
- SKILL.md 決策樹更新：首次串接→02a、App→02c、Apple Pay→02c
- SKILL.md 檔案索引新增 02a/02b/02c
- validate.yml 觸發條件：4 個 SETUP → 統一 SETUP.md
- guides/15 交叉引用：4 處 anchor 更新指向正確子指南
- CONTRIBUTING.md / PR Template：版本同步清單簡化

---

## [1.0.0] — 2026-03-14

### 新增
- 初始版本發布
- 25 份整合指南（guides/00–24），涵蓋金流、物流、電子發票、電子票證、直播收款、離線 POS
- 19 份即時 API 規格 URL 索引 + 1 份索引說明（references/），共 431 個官方文件 URL
- 12 種程式語言的加密實作（guides/13、14）與 E2E 範例（guides/24）
- 18 個加密測試向量（test-vectors/），含 CheckMacValue SHA256/MD5、AES-128-CBC、URL 編碼
- 多平台入口：SKILL.md（Claude/Copilot/Cursor）、SKILL_OPENAI.md（ChatGPT GPTs）、AGENTS.md（Codex CLI）、GEMINI.md（Gemini CLI）
- 6 個 Claude Code 快速指令（commands/）
- CI 自動驗證：AI Section Index 行號驗證（validate.yml）、每週 URL 可達性驗證（validate-references.yml）
- 官方 ECPay PHP SDK 134 個範例（scripts/SDK_PHP/），唯讀參考

### 版本相容性說明
- V1.0 API 規格以 SNAPSHOT 2026-03 為基準，正式上線前建議透過 references/ 取得即時規格
- 測試帳號與端點詳見 guides/00 與各 Setup guide

---

## 破壞性變更追蹤政策

當發生以下情況時，視為破壞性變更（Major version bump）：
- 移除現有 guide 檔案或大幅重構目錄結構
- 更改 SKILL.md 主要決策樹邏輯
- 測試向量答案變更（可能影響現有整合的正確性驗證）

破壞性變更會提前在 guides/00 頂部列出，並在此記錄。

---

[Unreleased]: https://github.com/ECPay/ECPay-API-Skill/compare/v1.5.4...HEAD
[1.5.4]: https://github.com/ECPay/ECPay-API-Skill/compare/v1.5.3...v1.5.4
[1.5.3]: https://github.com/ECPay/ECPay-API-Skill/compare/v1.5.2...v1.5.3
[1.5.2]: https://github.com/ECPay/ECPay-API-Skill/compare/v1.5.1...v1.5.2
[1.5.1]: https://github.com/ECPay/ECPay-API-Skill/compare/v1.5.0...v1.5.1
[1.5.0]: https://github.com/ECPay/ECPay-API-Skill/compare/v1.1.0...v1.5.0
[1.1.0]: https://github.com/ECPay/ECPay-API-Skill/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/ECPay/ECPay-API-Skill/releases/tag/v1.0.0
