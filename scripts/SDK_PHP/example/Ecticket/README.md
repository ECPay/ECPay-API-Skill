# Ecticket PHP 範例

ECPay 官方 PHP SDK v4.x 未包含電子票證（Ecticket）範例。

> ⚠️ **重要**：電子票證**不可**直接套用 B2C 發票的 `PostWithAesJsonResponseService`。
> ECTicket 每個請求都需要額外的 **CheckMacValue**，必須使用：
> - `AesService::class` 手動加密 Data
> - `JsonCurlService` 手動發送（或直接 curl）
> - 外層 JSON 加入 `CheckMacValue`（ECTicket 公式：`HashKey + Data明文JSON + HashIV`，與 AIO 不同——詳見 [guides/09](../../../../guides/09-ecticket.md)）
>
> 完整整合範例見 [guides/09 電子票券指南](../../../../guides/09-ecticket.md)。
> 參數規格見 `references/Ecticket/` 中各 API 技術文件。
