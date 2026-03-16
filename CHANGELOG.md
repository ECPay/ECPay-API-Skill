# Changelog

所有重要的版本變更都記錄在此。格式遵循 [Keep a Changelog](https://keepachangelog.com/zh-TW/1.0.0/)。

---

## [Unreleased]

---

## [1.0.0] — 2026-03-14

### 新增
- 初始版本發布
- 25 份整合指南（guides/00–24），涵蓋金流、物流、電子發票、電子票證、直播收款、離線 POS
- 19 份即時 API 規格 URL 索引（references/），共 431 個官方文件 URL
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

[Unreleased]: https://github.com/ECPay/ECPay-API-Skill/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/ECPay/ECPay-API-Skill/releases/tag/v1.0.0
