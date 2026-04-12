> 對應 ECPay API 版本 | 最後更新：2026-03

# 本地開發環境設定指南

> 📌 **為何需要這份指南？**
> ECPay Callback（ReturnURL / OrderResultURL）只能送達**公開可訪問的 URL**（不支援 localhost）。
> 在本地開發時，需要一個「隧道工具」將公開 URL 轉發到你的 localhost。

## 快速選擇

| 工具 | 安裝難度 | 免費方案限制 | 推薦場景 |
|------|:------:|---------|---------|
| **ngrok** | ★☆☆ | URL 每次重啟都變 | 個人開發、快速測試 |
| **Cloudflare Tunnel** | ★★☆ | 免費，URL 固定（需 Cloudflare 帳號） | 長期開發、URL 穩定 |
| **localtunnel** | ★☆☆ | URL 每次重啟都變（有時不穩定） | 零安裝快速測試 |
| **RequestBin** | ★☆☆ | 只能查看 Callback，無法執行業務邏輯 | 確認 Callback 結構 |

> **推薦**：個人開發用 **ngrok**（穩定，文件豐富）；長期開發用 **Cloudflare Tunnel**（免費且 URL 固定）。

---

## 方案 A：ngrok（最常用）

### 安裝

```bash
# macOS（Homebrew）
brew install ngrok

# Windows（Chocolatey）
choco install ngrok

# Linux
curl -s https://ngrok-agent.s3.amazonaws.com/ngrok.asc | sudo tee /etc/apt/trusted.gpg.d/ngrok.asc
echo "deb https://ngrok-agent.s3.amazonaws.com buster main" | sudo tee /etc/apt/sources.list.d/ngrok.list
sudo apt update && sudo apt install ngrok

# 直接下載（所有平台）
# https://ngrok.com/download
```

### 啟動隧道

```bash
# 將 localhost:3000 暴露到公開 URL（改為你的本地 port）
ngrok http 3000

# 啟動後會看到：
# Forwarding  https://a1b2c3d4.ngrok-free.app -> http://localhost:3000
#                      ↑ 這個就是你的公開 URL，複製作為 ReturnURL 前綴
```

### 使用 ngrok URL 設定 ECPay Callback

```python
# Python 範例
NGROK_URL = "https://a1b2c3d4.ngrok-free.app"  # 每次重啟 ngrok 後更新這裡

data = {
    "ReturnURL": f"{NGROK_URL}/ecpay/notify",        # Server 端 Callback
    "OrderResultURL": f"{NGROK_URL}/ecpay/result",   # 消費者前端跳轉
}
```

### ⚠️ ngrok 免費方案的限制

| 限制 | 說明 | 解法 |
|------|------|------|
| URL 每次重啟都變 | `ngrok http 3000` 每次產生不同的 URL | 每次重啟後更新 ReturnURL 並重新建立 Token（站內付 2.0）|
| 連線數限制 | 免費方案每月有連線次數上限 | 本地測試夠用；大量測試升級方案 |
| 速度限制 | 免費方案較慢 | ECPay Callback 通常只有 1-2KB，速度足夠 |

### ngrok 固定 URL（付費方案）

```bash
# 免費方案也可以設定固定域名（需先在 ngrok 控制台建立）
ngrok http --hostname=你的自訂名稱.ngrok-free.app 3000
```

---

## 方案 B：Cloudflare Tunnel（推薦長期使用）

### 優點：URL 固定，免費，穩定

```bash
# 安裝 cloudflared
# macOS
brew install cloudflare/cloudflare/cloudflared

# Windows（PowerShell 管理員）
winget install --id Cloudflare.cloudflared

# Linux
curl -L https://pkg.cloudflare.com/cloudflare-main.gpg | sudo tee /usr/share/keyrings/cloudflare-main.gpg
echo 'deb [signed-by=/usr/share/keyrings/cloudflare-main.gpg] https://pkg.cloudflare.com/cloudflared focal main' | sudo tee /etc/apt/sources.list.d/cloudflare-main.list
sudo apt-get update && sudo apt-get install cloudflared

# 登入（需要 Cloudflare 帳號，免費）
cloudflared tunnel login
```

### 建立固定隧道

```bash
# 建立隧道（只需做一次）
cloudflared tunnel create ecpay-dev

# 查看隧道 ID
cloudflared tunnel list

# 建立設定檔 ~/.cloudflared/config.yml
```

```yaml
# ~/.cloudflared/config.yml
tunnel: <你的 tunnel ID>
credentials-file: /Users/你的用戶名/.cloudflared/<tunnel-id>.json

ingress:
  - hostname: ecpay-dev.你的域名.workers.dev  # 或自訂域名
    service: http://localhost:3000
  - service: http_status:404
```

```bash
# 啟動隧道
cloudflared tunnel run ecpay-dev

# 你的固定 URL：https://ecpay-dev.你的域名.workers.dev
```

---

## 方案 C：localtunnel（零安裝）

```bash
# 需要 Node.js
npx localtunnel --port 3000

# 輸出：your url is: https://thick-rocks-walk.loca.lt
```

> ⚠️ localtunnel 有時不穩定，連線可能中斷。僅用於臨時測試。

---

## 方案 D：RequestBin（只看 Callback，不執行邏輯）

> 適用場景：尚未實作 Callback handler，但想先確認 ECPay 發送的 Callback 結構。

1. 前往 [https://requestbin.com](https://requestbin.com)，建立一個 Bin
2. 複製 Bin URL（如 `https://en4wqfq2o0a9.x.pipedream.net`）
3. 設為 ReturnURL 進行測試
4. 在 RequestBin 介面查看 ECPay 發送的 Callback headers 和 body

> **限制**：RequestBin 不回應 `1|OK`，ECPay 會視為失敗並重試最多 4 次。僅用於**觀察**，不用於正式測試。

---

## Callback 本地接收確認步驟

不論使用哪個工具，按以下步驟確認設定正確：

```bash
# 步驟 1：確認隧道可從外部訪問
curl -X POST https://你的隧道URL/ecpay/callback -d "test=1"
# 應得到 HTTP 200 的回應（即使 body 為 404 頁面也代表隧道通了）

# 步驟 2：確認你的應用程式在接收請求
# 在 handler 加入 log，發起測試交易，查看 log 是否出現
```

## 常見問題

### Q：一直 ngrok URL 過期怎麼辦？

```bash
# 每次重啟 ngrok 後，必須：
# 1. 更新 .env 或設定檔中的 ReturnURL
ECPAY_RETURN_URL=https://新URL.ngrok-free.app/ecpay/notify

# 2. 站內付 2.0：重新呼叫 GetTokenbyTrade（Token 包含 ReturnURL）
# 3. AIO 金流：下一次建單時自動帶入新 URL，不需其他操作
```

### Q：ECPay 說我的 Callback URL 無效？

```
確認項目：
✅ URL 以 https:// 開頭（不可 http://，自簽憑證會被拒）
✅ Port 為 443（80 用 http）
✅ URL 可從外部訪問（用 curl 從另一台機器測試）
✅ ngrok/隧道是否仍在運行（進程有時會崩潰）
```

### Q：Docker 環境中如何接收 Callback？

```bash
# Docker Compose 環境中，ngrok 需要在宿主機運行，指向宿主機 IP：port
# 或在 docker-compose.yml 中加入 ngrok 容器：

services:
  ngrok:
    image: ngrok/ngrok:latest
    command: http app:3000  # app 是你的服務名稱
    ports:
      - "4040:4040"  # ngrok 管理介面
    environment:
      - NGROK_AUTHTOKEN=${NGROK_AUTHTOKEN}
```

---

## 相關文件

- [guides/02a §本地開發環境快速設定](./02a-ecpg-quickstart.md) — ECPG 站內付 2.0 的 ngrok 設定
- [guides/15 §2 ReturnURL 收不到通知](./15-troubleshooting.md#2-returnurl-收不到通知) — 進階排查
- [guides/16 §紅燈檢查](./16-go-live-checklist.md) — 上線時需切換回正式 URL
