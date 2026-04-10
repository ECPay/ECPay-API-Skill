# 測試向量（Test Vectors）

> **所有人一分鐘看懂**：這是一組「加密考題 + 標準答案」，用來確保 ECPay Skill 教客戶寫的加密程式碼，在 12 種程式語言上都能算出正確的結果。每次 Skill 更新後自動跑一次，21 組全部通過才能發布。

---

## 🤔 這是什麼？用一個比喻

想像你經營一家連鎖烘焙教室，在全球 12 個國家開分店，每間教學員做同一款招牌蛋糕。你寫了 12 本食譜（= 12 種程式語言的綠界整合指南），每本都有「打蛋、加糖、加麵粉」的步驟。

但問題是：**每個國家的麵粉規格、烤箱刻度、甚至砂糖顆粒大小都不同**——如果某本食譜寫錯一個細節，那個國家的學員做出來的蛋糕就會失敗。

所以你額外準備了 **21 個「標準蛋糕範本」**（= 21 個測試向量）：每個範本都明確寫著「用這些材料、照這個步驟，成品應該長這樣」。每次你更新食譜之後，就照 12 本食譜各做一份，跟 21 個標準範本比對：

- ✅ 全部對得上 → 12 本食譜都正確，可以發布
- ❌ 有一個對不上 → 某本食譜有 bug，不能發布

ECPay Skill 的 `test-vectors/` 資料夾就是那 21 個標準範本。`verify.py` 就是那個「照食譜做一份再比對」的動作。

---

## ❓ 為什麼綠界 API 特別需要這個？

綠界的 API 規定：**你在送出任何請求前，必須先算出一個簽章**，叫做 `CheckMacValue`。算法流程是：

1. 把所有參數依**字母排序**（例如 `Amount=100&MerchantID=3002607`，不能顛倒）
2. 用**特定的 URL 編碼規則**處理特殊字元
3. 前後接上你的 `HashKey` 和 `HashIV`
4. 做 SHA256 雜湊，結果轉大寫

聽起來不難，但**魔鬼藏在 URL 編碼的細節裡**。每個程式語言的預設行為都不一樣：

| 情境 | PHP | Python | Node.js | Go | Java |
|---|---|---|---|---|---|
| 空格 | `+` | `+` | **`%20`** ⚠️ | `+` | `+` |
| `!` 驚嘆號 | `%21` | `%21` | **不編碼** ⚠️ | `%21` | `%21` |
| `'` 單引號 | `%27` | `%27` | **不編碼** ⚠️ | `%27` | `%27` |
| `~` 波浪號 | 不編碼 | 不編碼 | 不編碼 | 不編碼 | 不編碼 |

綠界要的是「空格變 `+`、`!` 變 `%21`、`'` 變 `%27`」這個特定規則。**Node.js 的 `encodeURIComponent` 對 `'` 和 `!` 不編碼，直接拿來用就會算出錯誤的簽章**，送到綠界後馬上收到 `CheckMacValue Error`，整筆交易失敗。

Skill 在 `guides/13-checkmacvalue.md` 和 `guides/14-aes-encryption.md` 教了 **12 種語言**各自的「正確寫法」。只要其中**一種語言漏了一個字元處理**，使用那種語言的客戶就會卡住。

## 🎯 21 組測試向量就是用來抓這種 bug 的

我們從 ECPay 官方 SDK 抽出 21 組真實資料，每組都有：
- **輸入**：某個交易參數組合
- **標準答案**：官方認定的正確 CheckMacValue / AES 結果

每次有人修改 Skill 後，執行：

```bash
pip install pycryptodome
python test-vectors/verify.py
```

腳本會：
1. 照 Skill 裡教的 Python 演算法實際算一遍每一組
2. 跟標準答案比對
3. 只要有一組對不上，立刻紅燈報錯，指出是哪一組、差在哪裡

**21/21 全數通過 = Skill 教的加密演算法是對的**，拿到這套 Skill 的客戶寫出來的程式碼都能通過綠界 API 的驗證。

---

## 🧯 如果沒有測試向量會怎樣？

這是真實可能發生的場景：

### 情境 A：沒有測試向量把關

1. Skill 維護者改 `guides/13-checkmacvalue.md` 的 Python 範例，不小心把 `urllib.parse.quote_plus` 打成 `urllib.parse.quote`（差一個 `_plus`）
2. Code review 肉眼看不出這種細節差異，PR 合併，發布 V2.8
3. 客戶升級後，AI 照新版教法產生 Python 程式碼，部署到正式環境
4. 客戶第一筆真實付款交易：綠界回應 `10200073 CheckMacValue Error`
5. 客戶打電話給 FAE：「你們的 Skill 有 bug！交易做不出來！」
6. FAE 花 2 小時追蹤，終於發現是 `guides/13` Python 範例漏了 `_plus`
7. 急件修復、道歉、發 V2.8.1 Hotfix、客戶也要跟著升級

### 情境 B：有測試向量 + CI 自動把關

1. Skill 維護者改 `guides/13`，不小心把 `quote_plus` 打成 `quote`
2. 一 push，CI 大約 5 秒後紅燈報錯：
   ```
   ❌ Vector 5: 空格處理測試（%20 vs + 陷阱）FAILED
     Expected: "abc+def"
     Got:      "abc%20def"
   ```
3. PR 自動被擋下，維護者改回 `quote_plus`，再 push 一次，CI 綠燈
4. **客戶完全感知不到這次差點出的 bug**

---

## 👥 所以，這跟我有關嗎？

| 你的角色 | 你需要知道的事 |
|---|---|
| **業務 / PM / 主管** | ❌ 不用動手。但可以知道「我們有 21 組自動化加密測試把關，Skill 每次發布前都要全數通過才能上架」——這是內部品質保證機制，客戶或主管問起時可以自信地引用 |
| **FAE / 客戶支援** | ❌ 不用動手。客戶問「你們的 Skill 會不會有加密 bug？」時，你可以回答：**「Skill 每次發布前會自動跑 21 組跨語言加密測試向量（CheckMacValue、AES、URL Encode 三大類），全數通過才能發布。任何加密演算法錯誤會在 CI 階段就被攔下，不會進入正式環境。」** |
| **使用 Skill 串接綠界的客戶工程師** | ❌ 不用自己跑。但你**可以參考** `test-vectors/*.json`：裡面的每一組都有明確的「輸入 → 預期輸出」，複製過來就能當自家程式碼的單元測試 |
| **Skill 維護者** | ✅ **必須跑**。修改 `guides/13`、`guides/14`、`guides/24` 或 `scripts/SDK_PHP/` 後，commit 前請先在本地執行 `python test-vectors/verify.py` 確認 21/21 通過。CI 也會在 push 後再把關一次 |

---

## ❓ 為什麼叫「向量」這麼抽象的名字？

「測試向量（test vector）」是**密碼學界的專有名詞**，指一組「已知輸入 + 預期輸出」的驗證資料，用來確認加密演算法的實作是否正確。這個詞從 AES、SHA-256 等國際標準的驗收測試沿用了幾十年。

**它不是數學或物理裡的「向量」**，只是一個技術詞彙。你可以直接把它理解成「標準試題」或「測試案例」，只是密碼學領域習慣用「向量」這個字眼。

---

## 📋 21 組向量清單

### 1️⃣ CheckMacValue 驗證（8 組）

| # | 名稱 | 驗證重點 |
|---|---|---|
| 1 | SHA256 基本測試 | 標準 AIO 金流流程 |
| 2 | MD5 測試 | 國內物流（MD5 是舊版協定，新 API 用 SHA256） |
| 3 | 特殊字元 `'` | Node.js / TypeScript 的 `encodeURIComponent` 不編碼單引號的陷阱 |
| 4 | 特殊字元 `~` | 各語言 `~` → `%7E` 替換處理 |
| 5 | **空格處理** | `%20` vs `+` 陷阱（Node.js、Rust 預設產生 `%20`，必須手動替換為 `+`） |
| 6 | **Callback 驗證** | 模擬收到付款通知，驗證 CMV 比對流程（反向驗證） |
| 7 | **E-Ticket CMV** | 電子票證用完全不同的公式：`SHA256(toLowerCase(URL_encode(Key+JSON+IV)))`，**和 AIO 的 CMV 不相容，不可混用** |
| 8 | **AllowanceByCollegiate MD5** | B2C 發票唯一帶 CMV 的 API，使用 MD5 + 發票帳號（不是 AIO 金流帳號） |

### 2️⃣ AES 加密 / 解密（9 組，含 2 組說明性向量）

| # | 名稱 | 驗證重點 |
|---|---|---|
| 1 | 基本測試（插入順序 JSON key） | Python / Node.js / C# / Ruby 等「插入順序」語言 |
| 2 | 基本測試（字母序 JSON key） | Go / Java / Swift 等「字母序」語言（Go 的 `map` 和 Java 的 `HashMap` 會自動排序 key） |
| 3 | 特殊字元測試（`!*'()~`） | 各語言 URL encode 細節差異 |
| 4 | **PKCS7 16-byte 邊界** | plaintext 剛好 32 bytes 時的 padding 行為（整塊邊界陷阱） |
| 5 | **UTF-8 中文字元** | `json.dumps(ensure_ascii=False)` / Go `SetEscapeHTML(false)` 驗證 |
| 6 | **AES 解密（反向驗證）** | Base64 → decrypt → URL decode → JSON（完整 callback 解密流程） |
| 7 | 📖 **alphabetic-key-order-go-java**（說明性） | 不執行驗證，純粹提供情境對照說明 Go/Java HashMap key 排序差異與應對方式 |
| 8 | 📖 **pkcs7-exact-block-boundary**（說明性） | 不執行驗證，純粹說明 PKCS7 整塊邊界的 padding 行為 |
| 9 | **ECPG 金流帳號測試** | GetTokenbyTrade 請求格式（站內付 2.0 Token 生成） |

> 📖 **什麼是「說明性向量」？**：Vector 7 和 8 不做實際的加密計算驗證，而是在 JSON 裡詳細描述一種**跨語言踩坑情境**，供 `guides/13`/`14`/`24` 交叉引用。它們是為了教學而存在的「案例說明」，不是斷言。verify.py 執行時會顯示 `SKIP (explanatory)`。

### 3️⃣ URL Encode 函式比對（4 組）

> **背景**：ECPay 有兩個**不同的** URL Encode 函式。一個給 CheckMacValue 用（叫 `ecpayUrlEncode`），一個給 AES 加密用（叫 `aesUrlEncode`）。規則不同，**絕對不可混用**。這 4 組向量對比兩個函式對同一輸入的不同輸出結果。

| # | 輸入 | 驗證重點 |
|---|---|---|
| 1 | `Items (Special)~Test` | `(` `)` 的 .NET 特殊字元替換 + 大小寫差異 |
| 2 | `Tom's Shop!` | `!` 的 .NET 特殊字元替換 + 大小寫差異 |
| 3 | `price=100&item=test*2` | `*` 的 .NET 特殊字元替換（hex 含字母：`%2A` → `*`） |
| 4 | `file_name-v2.0` | 結果相同的情境（⚠️ **即使結果相同也不可混用**，因為其他輸入會不同） |

> **⚠️ 核心警告**：`ecpayUrlEncode`（給 CheckMacValue 用）和 `aesUrlEncode`（給 AES 加密用）是**兩個不同的函式**，處理規則不同，**絕對不可混用**。這是跨語言串接最常見的陷阱之一。詳見 [url-encode-comparison.json](url-encode-comparison.json) 與 [guides/14 §AES vs CMV URL Encode 對比表](../guides/14-aes-encryption.md)。

---

## 💻 如何執行

### 本機單次驗證

```bash
# 安裝依賴（只需一次）
pip install pycryptodome

# 從 repo 根目錄執行
python test-vectors/verify.py
```

預期輸出：
```
CheckMacValue Vectors
  Vector 1: PASS ✓ | SHA256 基本測試（AIO 金流）
  ...
AES Encryption/Decryption Vectors
  ...
URL Encode Comparison Vectors
  ...

============================================================
Total: 8 CMV + 9 AES + 4 URL encode = 21 vectors
ALL PASSED ✓
```

### CI 自動執行

`.github/workflows/validate.yml` 的最後一步會自動執行 `python test-vectors/verify.py`，所以：
- Push 任何 commit → CI 自動跑 → 失敗會在 commit 旁顯示紅燈
- 開 PR → CI 自動跑 → 失敗會擋住合併

---

## 📁 檔案說明

| 檔案 | 角色 | 給誰看？ |
|---|---|---|
| `README.md` | 本檔案 — 完整白話說明 | 所有人 |
| `verify.py` | 主要驗證腳本，跑全部 21 組向量（Python 3，需 `pycryptodome`） | CI + 維護者 |
| `checkmacvalue.json` | CheckMacValue 8 組測試向量的「輸入 + 預期輸出」資料 | `verify.py` 讀取；維護者新增向量時編輯 |
| `aes-encryption.json` | AES 加密/解密 9 組測試向量資料 | 同上 |
| `url-encode-comparison.json` | URL Encode 函式比對 4 組向量資料 | 同上 |
| `verify-go.go` | Go 語言實作版驗證（選配） | 想在 Go 環境獨立跑驗證的開發者 |
| `verify-java.java` | Java 語言實作版驗證（選配） | Java 開發者 |
| `verify-csharp.cs` | C# 語言實作版驗證（選配） | .NET 開發者 |

> 三個 JSON 檔案是**人類可讀的資料檔**，`verify.py` 讀取這些資料並依 `guides/13` / `guides/14` 教的演算法實際計算，比對預期結果。三個 `verify-*.{go,java,cs}` 是選配的「第二意見」，讓你在對應語言環境裡獨立跑一遍驗證，雙重把關。

---

## 🛠️ 維護者：如何新增向量

當綠界新增 API、或發現新的跨語言陷阱時，依下列步驟新增向量：

1. 選擇對應的 JSON 檔：
   - CheckMacValue 相關 → `checkmacvalue.json`
   - AES 加密相關 → `aes-encryption.json`
   - URL Encode 差異相關 → `url-encode-comparison.json`
2. 新增一筆資料，必要欄位：`name`（向量描述）、`input`（輸入參數）、`expected`（預期輸出）
3. 本機執行 `python verify.py` 確認新向量通過（故意寫錯驗證一次會失敗也是好習慣，確認 assert 有生效）
4. 在對應的 guide（`guides/13-checkmacvalue.md` 或 `guides/14-aes-encryption.md`）新增 §測試向量 段落說明新增背景
5. 更新本 README 的向量清單表格（包含總數）
6. Commit message 建議格式：`chore(test-vectors): add vector #X for <scenario>`

---

## 📚 延伸閱讀

- [guides/13-checkmacvalue.md](../guides/13-checkmacvalue.md) §測試向量 — 12 語言 CheckMacValue 實作與每個向量的詳細計算步驟
- [guides/14-aes-encryption.md](../guides/14-aes-encryption.md) §測試向量 — AES 加密實作與 JSON key 順序 / PKCS7 padding 陷阱說明
- [guides/14-aes-encryption.md](../guides/14-aes-encryption.md) §AES vs CMV URL Encode 對比表 — 兩個 URL encode 函式的差異與「為什麼不可混用」
- [guides/20-http-protocol-reference.md](../guides/20-http-protocol-reference.md) §CheckMacValue 計算 — 跨語言 HTTP 協議層的計算規範
