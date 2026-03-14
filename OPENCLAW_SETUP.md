# OpenClaw 安裝指南

> **版本**：V1.0 | 對應 SKILL.md V1.0

> 將 ECPay Skill 安裝到 OpenClaw，讓 AI 助手（WhatsApp / Telegram / Slack / Discord 等任何頻道）具備完整的綠界 API 串接能力。
> 前置條件：Node ≥ 22、OpenClaw 已安裝（`npm install -g openclaw@latest`）。

## 步驟 1：安裝 OpenClaw（若尚未安裝）

```bash
npm install -g openclaw@latest
openclaw onboard --install-daemon
```

詳細說明：[docs.openclaw.ai/start/getting-started](https://docs.openclaw.ai/start/getting-started)

## 步驟 2：Clone ECPay Skill

### 方案 A：個人共用安裝（推薦）

所有 agent 共享，適合個人開發者：

```bash
git clone https://github.com/ECPay/ECPay-API-Skill.git ~/.openclaw/skills/ecpay
```

### 方案 B：Workspace 層級安裝

僅對當前 workspace 有效：

```bash
# 在你的 OpenClaw workspace 目錄下執行
git clone https://github.com/ECPay/ECPay-API-Skill.git skills/ecpay
```

### 方案 C：Git Submodule（團隊共用）

適合多人團隊，將 Skill 固定在版控中：

```bash
git submodule add https://github.com/ECPay/ECPay-API-Skill.git skills/ecpay
```

## 步驟 3：確認 Skill 已載入

OpenClaw 內建 **skills watcher**（預設啟用），會自動偵測新加入的 `SKILL.md`。
變更在**下一個新 session** 生效，不需要重啟 Gateway。

若 Gateway 尚未啟動，先啟動它：

```bash
openclaw gateway --port 18789
```

執行 doctor 可確認 Skill 載入狀態（第 11 項會列出 eligible/missing/blocked）：

```bash
openclaw doctor
```

## 步驟 4：驗證安裝

在任何已連接的頻道（WhatsApp / Telegram / Slack / Discord / LINE 等）傳送：

> 「綠界 AIO 金流的測試 MerchantID 是多少？」

預期回覆：**3002607**，並引用 guides/00 或 SKILL.md 中的測試帳號資訊。若回答正確，表示 Skill 已成功載入。

## 使用方式

安裝後直接在對話中提及 ECPay 關鍵字，AI 即自動啟動本 Skill：

```
ecpay、綠界、CheckMacValue、站內付、AIO、電子發票、超商取貨、物流串接...
```

### 範例對話

| 你說 | AI 做什麼 |
|------|----------|
| 「我要用 Node.js 串接綠界信用卡收款」 | 生成 AIO 完整串接程式碼 + 說明步驟 |
| 「CheckMacValue 驗證失敗，怎麼排查？」 | 診斷 6 步驟演算法、常見錯誤清單 |
| 「Python 的 AES 解密怎麼寫？」 | 給出帶測試向量的實作程式碼 |
| 「我想收款後自動開電子發票」 | 說明金流 + 發票串接流程 |

## 更新 Skill

```bash
cd ~/.openclaw/skills/ecpay
git pull origin main
```

更新後開啟新 session 即生效（skills watcher 自動偵測變更）。

## 常見問題

**Q：Skill 沒有被載入？**

確認路徑正確：`~/.openclaw/skills/ecpay/SKILL.md` 應存在。執行 `openclaw doctor` 查看 skill 載入狀態。

**Q：可以用在哪些頻道？**

WhatsApp、Telegram、Slack、Discord、Google Chat、LINE、Signal、iMessage、Microsoft Teams、Matrix 等全部 OpenClaw 支援的頻道均可使用。

**Q：如何與其他 Skill 共存？**

可以正常共存。若有多個支付服務 Skill，提問時加上「ECPay」或「綠界」確認使用哪個 Skill。

**Q：是否需要 API Key？**

本 Skill 是純知識文件，無需任何 API Key。使用的 LLM API Key 由你的 OpenClaw 設定決定。

---

> 技術支援：sysanalydep.sa@ecpay.com.tw
> OpenClaw 相關問題：[discord.gg/clawd](https://discord.gg/clawd)
