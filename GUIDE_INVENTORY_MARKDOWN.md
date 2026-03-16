# ECPay Skill Guide Files Inventory

**Generated**: 2026-03-16 14:12:57  
**Location**: C:\Code_Base\skills\ecpay-skill\guides/

---

## Quick Stats

| Metric | Value |
|--------|-------|
| **Main Guides (00-24)** | 25 files, 15,944 lines (avg 637.76 lines/file) |
| **Language Standards** | 12 files, 3,553 lines (avg 296.08 lines/file) |
| **Total Documentation** | 37 files, 19,497 lines |
| **Largest File** | 02-payment-ecpg.md (2,957 lines) |
| **Smallest File** | 18-livestream-payment.md (86 lines) |

---

## Main Guide Files (00-24)

### Payments & Authorization (01-03)
- **01-payment-aio.md** (960 lines)
  - All-in-one payment gateway integration
  - PHP(23), Python(2), Node.js(1), Java(1)
  - Source: references/Payment/全方位金流API技術文件

- **02-payment-ecpg.md** (2,957 lines) ⭐ LARGEST
  - In-page payment 2.0 (Web & App variants)
  - PHP(22), Python(16), Node.js(9), TypeScript(8), Java(9), Ruby(1)
  - Source: references/Payment/站內付2.0API技術文件

- **03-payment-backend.md** (461 lines)
  - Backend authorization & non-credit card payment
  - PHP(9)
  - Sources: Credit card backend auth + Non-credit card backend APIs

### Invoicing (04-05)
- **04-invoice-b2c.md** (866 lines)
  - B2C e-invoice integration
  - PHP(22), Python(2), TypeScript(1)
  - Source: references/Invoice/B2C電子發票介接技術文件

- **05-invoice-b2b.md** (712 lines)
  - B2B e-invoice (exchange & deposit modes)
  - PHP(16), TypeScript(1)
  - Source: references/Invoice/B2B電子發票API技術文件

### Logistics (06-08)
- **06-logistics-domestic.md** (563 lines)
  - Domestic logistics integration
  - PHP(20), Python(1), Node.js(1), Java(1)
  - Source: references/Logistics/物流整合API技術文件

- **07-logistics-allinone.md** (578 lines)
  - All-in-one logistics (AES-JSON protocol)
  - PHP(19), Python(1), Node.js(1), Java(1)
  - Source: references/Logistics/全方位物流服務API技術文件

- **08-logistics-crossborder.md** (258 lines) ⭐ SMALLEST MAIN
  - Cross-border logistics
  - PHP(8), Python(2)
  - Source: references/Logistics/綠界科技跨境物流API技術文件

### Payment Extensions (09-10)
- **09-ecticket.md** (749 lines)
  - Electronic ticket integration (deposit/distribution)
  - PHP(6), Python(3), Node.js(1), Java(1)
  - Sources: 3 ticket-related API documents

- **10-cart-plugins.md** (96 lines)
  - E-commerce cart integration (WooCommerce, OpenCart, Magento, Shopify)
  - No code examples (reference index)
  - Source: references/Cart/購物車設定說明

### Technical References (11-14)
- **11-cross-service-scenarios.md** (228 lines)
  - End-to-end integration scenarios
  - No code examples

- **12-sdk-reference.md** (200 lines)
  - PHP SDK reference guide
  - PHP(2)

- **13-checkmacvalue.md** (1,074 lines)
  - CheckMacValue encryption implementation
  - PHP(1), Python(2), Node.js(1), TypeScript(1), Java(2), C#(1), Go(1), Ruby(1)
  - **KEY: Cross-language encryption reference**

- **14-aes-encryption.md** (1,169 lines)
  - AES-128-CBC encryption implementation
  - PHP(1), Python(3), Node.js(2), TypeScript(2), Java(4), C#(2), Go(2), Ruby(2)
  - **KEY: Most comprehensive encryption guide**

### Operational Guides (15-16)
- **15-troubleshooting.md** (800 lines)
  - Debugging, error codes, common pitfalls
  - PHP(3), Python(2), Node.js(2), Java(2)
  - **KEY: Central reference for error resolution**

- **16-go-live-checklist.md** (209 lines)
  - Pre-production testing & deployment checklist
  - No code examples

### Specialized Payment (17-18)
- **17-pos-integration.md** (87 lines)
  - POS card reader integration
  - No code examples
  - Source: references/Payment/刷卡機POS串接規格

- **18-livestream-payment.md** (86 lines) ⭐ SMALLEST OVERALL
  - Live stream monetization
  - PHP(1)
  - Source: references/Payment/直播主收款網址串接技術文件

### Offline & Modern Integration (19-24)
- **19-invoice-offline.md** (200 lines)
  - Offline e-invoice
  - PHP(4)
  - Source: references/Invoice/離線電子發票API技術文件

- **20-http-protocol-reference.md** (711 lines)
  - Language-agnostic HTTP protocol reference
  - Covers: domains, endpoints, authentication, versioning
  - **KEY: Protocol foundation for non-PHP developers**

- **21-error-codes-reference.md** (276 lines)
  - Centralized error code lookup (all services)
  - No code examples
  - **KEY: Error diagnosis guide**

- **22-webhook-events-reference.md** (826 lines)
  - Unified callback/webhook format reference
  - PHP(7), Python(2), Node.js(2), Java(2), Go(1)
  - **KEY: Callback pattern implementations**

- **23-performance-scaling.md** (143 lines)
  - Performance optimization & scaling guidance
  - Python(1), Node.js(1), Java(1)

- **24-multi-language-integration.md** (790 lines)
  - E2E examples in Go, Java, C#, Kotlin
  - TypeScript(1), Go(2)
  - **KEY: Multi-language reference implementations**

---

## Language Standards Files (guides/lang-standards/)

Implementation guidelines for code generation across programming languages:

| Language | Lines | Code Blocks | Cross-references |
|----------|-------|------------|------------------|
| C        | 304   | 14         | guides/13, 14, 24 |
| C++      | 319   | 14         | guides/13, 14, 24 |
| C#       | 228   | 12         | guides/13, 14, 24 |
| Go       | 286   | 13         | guides/13, 14, 24 |
| Java     | 276   | 14         | guides/13, 14, 24 |
| Kotlin   | 247   | 13         | guides/13, 14, 24 |
| Node.js  | 233   | 13         | guides/13, 14, 00, 24 |
| Python   | 250   | 12         | guides/13, 14, 00, 24 |
| Ruby     | 267   | 13         | guides/13, 14, 24 |
| Rust     | 273   | 13         | guides/13, 14, 24 |
| Swift    | 334   | 13         | guides/13, 14, 24 |
| TypeScript | 237  | 11         | guides/13, 14, 24 |

**Key**: Each contains:
- Language-specific code examples
- Encryption implementations (CheckMacValue, AES)
- SDK usage patterns
- Best practices

---

## Content Interconnections

### Most Central Guides (Highest Cross-References)
1. **guides/14-aes-encryption.md** → Referenced by 10+ guides
   - Core cryptography foundation
   - Contains: Python, Node.js, TypeScript, Java, C#, Go, Ruby examples

2. **guides/15-troubleshooting.md** → Referenced by 10+ guides
   - Error resolution central hub
   - Links to: payment, logistics, invoicing, performance, scaling

3. **guides/20-http-protocol-reference.md** → Referenced by 8+ guides
   - HTTP foundation for non-SDK developers
   - Protocol specifications for all services

4. **guides/16-go-live-checklist.md** → Referenced by 9 guides
   - Production deployment requirements

### Reference Document Dependencies
**Most Referenced references/ Areas**:
1. **references/Payment/** (8+ references)
   - AIO, ECPG, Backend Auth, POS, Live Stream
2. **references/Logistics/** (6+ references)
   - Domestic, All-in-one, Cross-border
3. **references/Invoice/** (4+ references)
   - B2C, B2B, Offline
4. **references/Ecticket/** (3+ references)
   - Deposit, Distribution, Withdrawal

### Language Coverage by Guide
- **PHP**: Most comprehensive (18 files with examples)
- **Python**: 13 files (guides 00, 02, 04, 06-09, 13-15, 22-23)
- **Node.js/JavaScript**: 11 files (guides 00-02, 06-07, 09, 13-15, 22-23)
- **Java**: 8 files (guides 00-01, 06-07, 09, 13-15, 22-23)
- **TypeScript**: 7 files (guides 02, 04-05, 13-14, 24)
- **Go**: 5 files (guides 00, 13-14, 22, 24)
- **C#**: 3 files (guides 13-14)
- **Ruby**: 4 files (guides 02, 13-14, 22)
- **C, C++, Kotlin, Rust, Swift**: Language-standards only

---

## SNAPSHOT Indicators

**15 files contain SNAPSHOT blockquotes** (all dated 2026-03):

| Status | Files |
|--------|-------|
| ✅ SNAPSHOT | 00, 01, 02, 03, 04, 05, 06, 07, 08, 09, 10, 16, 17, 18, 19, 20(2x), 21, 22 |
| ⓘ No SNAPSHOT | 11, 12, 13, 14, 15, 23, 24 |

**Meaning**: Files with SNAPSHOT indicate content is based on official docs dated 2026-03 and may require web_fetch for latest specs.

---

## Key Characteristics

### File Size Distribution
- **0-200 lines**: 5 files (small references: 10, 17, 18, 19, 21)
- **200-500 lines**: 6 files (guides: 11, 12, 16, 23, lang-standards subset)
- **500-1000 lines**: 9 files (payment, logistics, troubleshooting)
- **1000+ lines**: 5 files (13, 14, 00, 01, 02)

### Code Example Concentration
- **Heavy code**: Guides 00-09, 13-14, 22 (many examples for practical reference)
- **Medium code**: Guides 12, 15, 23 (some examples)
- **Light/no code**: Guides 10-11, 16-21, 24 (reference/checklist oriented)

### Update Pattern
- All main guides last updated: 2026-03
- Header format consistent across all 25 main guides
- Language-standards files use different header format (title only)

---

## Usage Recommendations

**For API Integration**:
1. Start: guides/00 (overview) → guides/01-09 (service guides)
2. Reference: guides/13-14 (encryption), guides/20 (HTTP protocol)
3. Debug: guides/15 (troubleshooting), guides/21 (error codes)
4. Go live: guides/16 (checklist), guides/23 (performance)

**For Non-PHP Languages**:
1. Read: guides/20 (HTTP foundation)
2. Implement: guides/lang-standards/{language}.md
3. Encrypt: guides/13-14 with language-specific examples
4. Verify: guides/22 (webhook handling)

**For Multi-Service Integration**:
1. Reference: guides/11 (cross-service scenarios)
2. Protocol: guides/20 (HTTP), guides/14 (AES)
3. Callbacks: guides/22 (webhook events)
4. Checklist: guides/16 (go-live)

---
