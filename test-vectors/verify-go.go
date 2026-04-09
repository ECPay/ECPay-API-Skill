// ECPay API Test Vector Verification — Go
//
// Run from repo root:
//   go run test-vectors/verify-go.go
//
// Zero external dependencies. Requires Go 1.16+.
//
// ECPay-specific encoding notes:
//   - ecpayUrlEncode  : url.QueryEscape → replace ~ with %7E → lowercase → .NET char restoration
//   - aesUrlEncode    : url.QueryEscape → replace ~ with %7E  (no lowercase, no .NET restore)
//   - Space encodes to "+" via url.QueryEscape — matches PHP urlencode() behaviour (correct)
//   - AES key and IV: UTF-8 bytes, first 16 bytes only (AES-128)
//   - PKCS7 padding: if len % 16 == 0, pad_len = 16 (add a full extra block)

package main

import (
	"crypto/aes"
	"crypto/cipher"
	"crypto/md5"
	"crypto/sha256"
	"encoding/base64"
	"encoding/json"
	"fmt"
	"net/url"
	"os"
	"sort"
	"strings"
)

// ──────────────────────────────────────────────
// ecpayUrlEncode — used for CheckMacValue (CMV)
//
// Order of operations (mirrors PHP UrlService::ecpayUrlEncode):
//   1. url.QueryEscape  →  space becomes "+", standard percent-encoding
//   2. Replace "~" with "%7E"  (Go/PHP diverge: Go doesn't encode ~)
//   3. strings.ToLower  (entire string)
//   4. .NET-style char restoration: un-encode chars that .NET URLEncoder leaves literal
//      After lowercasing all hex is already lowercase, so patterns like %2d are safe to match.
// ──────────────────────────────────────────────
func ecpayUrlEncode(s string) string {
	encoded := url.QueryEscape(s)

	// Step 2: PHP urlencode encodes '~' but Go does not — normalise to %7E
	encoded = strings.ReplaceAll(encoded, "~", "%7E")

	// Step 3: lowercase (makes hex digits lowercase: %2D → %2d, etc.)
	encoded = strings.ToLower(encoded)

	// Step 4: .NET URLEncoder leaves these characters unencoded; replicate that behaviour.
	// All patterns are already lowercase after step 3, so matching is safe.
	for _, pair := range [][2]string{
		{"%2d", "-"},
		{"%5f", "_"},
		{"%2e", "."},
		{"%21", "!"},
		{"%2a", "*"},
		{"%28", "("},
		{"%29", ")"},
	} {
		encoded = strings.ReplaceAll(encoded, pair[0], pair[1])
	}
	return encoded
}

// ──────────────────────────────────────────────
// aesUrlEncode — used before AES encryption
//
// Identical to ecpayUrlEncode EXCEPT:
//   - No strings.ToLower step
//   - No .NET char restoration
// The ~ replacement still applies (uppercase %7E to match ECPay expectation).
// ──────────────────────────────────────────────
func aesUrlEncode(s string) string {
	encoded := url.QueryEscape(s)
	// Replace '~' with uppercase %7E (Go does not percent-encode ~ by default)
	encoded = strings.ReplaceAll(encoded, "~", "%7E")
	return encoded
}

// ──────────────────────────────────────────────
// calcCheckMacValue — standard CMV (AIO payment, logistics, invoice callback)
//
// Algorithm:
//   1. Sort params case-insensitively by key
//   2. Build: HashKey={k}&{sorted_params}&HashIV={iv}
//   3. ecpayUrlEncode the whole string
//   4. SHA256 or MD5, uppercase hex
// ──────────────────────────────────────────────
func calcCheckMacValue(hashKey, hashIV string, params map[string]string, method string) string {
	// Collect and sort keys case-insensitively
	keys := make([]string, 0, len(params))
	for k := range params {
		keys = append(keys, k)
	}
	sort.Slice(keys, func(i, j int) bool {
		return strings.ToLower(keys[i]) < strings.ToLower(keys[j])
	})

	// Build the raw string
	parts := make([]string, 0, len(params))
	for _, k := range keys {
		parts = append(parts, k+"="+params[k])
	}
	raw := "HashKey=" + hashKey + "&" + strings.Join(parts, "&") + "&HashIV=" + hashIV

	// Apply CMV URL encoding
	encoded := ecpayUrlEncode(raw)

	// Hash
	switch strings.ToUpper(method) {
	case "MD5":
		sum := md5.Sum([]byte(encoded))
		return strings.ToUpper(fmt.Sprintf("%x", sum))
	default: // SHA256
		sum := sha256.Sum256([]byte(encoded))
		return strings.ToUpper(fmt.Sprintf("%x", sum))
	}
}

// ──────────────────────────────────────────────
// calcEcticketCMV — E-Ticket CMV (different formula, no param sorting)
//
// Algorithm (per official ECPay E-Ticket docs):
//   1. Concatenate: hashKey + plaintext_json + hashIV
//   2. aesUrlEncode the concatenated string
//   3. strings.ToLower (E-Ticket adds lowercase AFTER URL encode, unlike CMV which encodes first)
//   4. SHA256, uppercase hex
// ──────────────────────────────────────────────
func calcEcticketCMV(hashKey, hashIV, plaintextJSON string) string {
	raw := hashKey + plaintextJSON + hashIV
	encoded := strings.ToLower(aesUrlEncode(raw))
	sum := sha256.Sum256([]byte(encoded))
	return strings.ToUpper(fmt.Sprintf("%x", sum))
}

// ──────────────────────────────────────────────
// pkcs7Pad — PKCS7 padding to 16-byte boundary.
// If len(data) is already a multiple of 16, add a full 16-byte padding block.
// ──────────────────────────────────────────────
func pkcs7Pad(data []byte) []byte {
	padLen := 16 - (len(data) % 16)
	// padLen is in [1..16]; when len%16==0 it correctly becomes 16
	padding := make([]byte, padLen)
	for i := range padding {
		padding[i] = byte(padLen)
	}
	return append(data, padding...)
}

// ──────────────────────────────────────────────
// pkcs7Unpad — remove PKCS7 padding, return error if invalid.
// ──────────────────────────────────────────────
func pkcs7Unpad(data []byte) ([]byte, error) {
	if len(data) == 0 {
		return nil, fmt.Errorf("empty data")
	}
	padLen := int(data[len(data)-1])
	if padLen < 1 || padLen > 16 {
		return nil, fmt.Errorf("invalid PKCS7 pad length: %d", padLen)
	}
	if len(data) < padLen {
		return nil, fmt.Errorf("data shorter than pad length")
	}
	for i := len(data) - padLen; i < len(data); i++ {
		if data[i] != byte(padLen) {
			return nil, fmt.Errorf("invalid PKCS7 padding bytes")
		}
	}
	return data[:len(data)-padLen], nil
}

// ──────────────────────────────────────────────
// aesEncrypt — ECPay AES-128-CBC encryption
//
// Flow:
//   1. aesUrlEncode(plaintextJSON)
//   2. PKCS7 pad the UTF-8 bytes
//   3. AES-128-CBC with key[:16] and IV[:16] (UTF-8 bytes)
//   4. Standard Base64 encode (not URL-safe)
//
// NOTE: Go map iteration order is random. If your plaintext_json comes from
// encoding a map[string]interface{}, keys will be sorted alphabetically by
// encoding/json. The test vectors reflect this; vector 2 uses alphabetical order.
// ──────────────────────────────────────────────
func aesEncrypt(plaintextJSON, hashKey, hashIV string) (string, string, error) {
	urlEncoded := aesUrlEncode(plaintextJSON)
	padded := pkcs7Pad([]byte(urlEncoded))

	block, err := aes.NewCipher([]byte(hashKey)[:16])
	if err != nil {
		return "", "", fmt.Errorf("aes.NewCipher: %w", err)
	}
	ciphertext := make([]byte, len(padded))
	mode := cipher.NewCBCEncrypter(block, []byte(hashIV)[:16])
	mode.CryptBlocks(ciphertext, padded)

	return base64.StdEncoding.EncodeToString(ciphertext), urlEncoded, nil
}

// ──────────────────────────────────────────────
// aesDecrypt — ECPay AES-128-CBC decryption
//
// Flow:
//   1. Standard Base64 decode
//   2. AES-128-CBC decrypt with key[:16] and IV[:16]
//   3. Remove PKCS7 padding
//   4. Return the raw UTF-8 string (still URL-encoded at this point)
//
// The caller is responsible for the final URL-decode step (url.QueryUnescape).
// This matches the Python reference: aes_decrypt() returns the URL-encoded string;
// the test runner then calls urllib.parse.unquote_plus() separately to get JSON.
// ──────────────────────────────────────────────
func aesDecrypt(encryptedB64, hashKey, hashIV string) (string, error) {
	ciphertext, err := base64.StdEncoding.DecodeString(encryptedB64)
	if err != nil {
		return "", fmt.Errorf("base64 decode: %w", err)
	}

	block, err := aes.NewCipher([]byte(hashKey)[:16])
	if err != nil {
		return "", fmt.Errorf("aes.NewCipher: %w", err)
	}
	if len(ciphertext)%aes.BlockSize != 0 {
		return "", fmt.Errorf("ciphertext length %d not a multiple of block size", len(ciphertext))
	}
	mode := cipher.NewCBCDecrypter(block, []byte(hashIV)[:16])
	plaintext := make([]byte, len(ciphertext))
	mode.CryptBlocks(plaintext, ciphertext)

	unpadded, err := pkcs7Unpad(plaintext)
	if err != nil {
		return "", fmt.Errorf("pkcs7Unpad: %w", err)
	}

	// Return the URL-encoded string as-is — caller applies url.QueryUnescape.
	return string(unpadded), nil
}

// ──────────────────────────────────────────────
// Test runner helpers
// ──────────────────────────────────────────────

var failures int

func check(label, expected, actual string) {
	if expected == actual {
		fmt.Printf("    %s: PASS\n", label)
	} else {
		failures++
		fmt.Printf("    %s: FAIL\n", label)
		fmt.Printf("      Expected: %s\n", expected)
		fmt.Printf("      Got:      %s\n", actual)
	}
}

func checkInt(label string, expected, actual int) {
	if expected == actual {
		fmt.Printf("    %s: PASS\n", label)
	} else {
		failures++
		fmt.Printf("    %s: FAIL\n", label)
		fmt.Printf("      Expected: %d\n", expected)
		fmt.Printf("      Got:      %d\n", actual)
	}
}

// ──────────────────────────────────────────────
// JSON schema types (mirrors the .json test vector files)
// ──────────────────────────────────────────────

type CMVVector struct {
	Name              string            `json:"name"`
	Method            string            `json:"method"`
	HashKey           string            `json:"hashKey"`
	HashIV            string            `json:"hashIV"`
	Formula           string            `json:"formula"`
	Params            map[string]string `json:"params"`
	PlaintextJSON     string            `json:"plaintext_json"`
	Expected          string            `json:"expected"`
	WrongWithPercent20 string           `json:"wrong_with_percent20"`
}

type CMVFile struct {
	Vectors []CMVVector `json:"vectors"`
}

type AESVector struct {
	Name                    string `json:"name"`
	HashKey                 string `json:"hashKey"`
	HashIV                  string `json:"hashIV"`
	Direction               string `json:"direction"`
	PlaintextJSON           string `json:"plaintext_json"`
	EncryptedBase64         string `json:"encrypted_base64"`
	ExpectedURLEncoded      string `json:"expected_url_encoded"`
	ExpectedURLEncodedLength int   `json:"expected_url_encoded_length"`
	ExpectedBase64          string `json:"expected_base64"`
	ExpectedDecrypted       string `json:"expected_decrypted"`
	ExpectedJSON            string `json:"expected_json"`
}

type AESFile struct {
	Vectors []AESVector `json:"vectors"`
}

type UEVector struct {
	Input          string `json:"input"`
	EcpayURLEncode string `json:"ecpayUrlEncode"`
	AESURLEncode   string `json:"aesUrlEncode"`
}

type UEFile struct {
	Vectors []UEVector `json:"vectors"`
}

// ──────────────────────────────────────────────
// main
// ──────────────────────────────────────────────

func main() {
	// Verify we are running from the repo root
	for _, p := range []string{
		"test-vectors/checkmacvalue.json",
		"test-vectors/aes-encryption.json",
		"test-vectors/url-encode-comparison.json",
	} {
		if _, err := os.Stat(p); err != nil {
			fmt.Fprintf(os.Stderr, "Error: %s not found. Please run from ecpay-skill root directory.\n", p)
			os.Exit(1)
		}
	}

	// ── CheckMacValue Vectors ──────────────────
	fmt.Println(strings.Repeat("=", 60))
	fmt.Println("CheckMacValue Vectors")
	fmt.Println(strings.Repeat("=", 60))

	cmvRaw, _ := os.ReadFile("test-vectors/checkmacvalue.json")
	var cmvFile CMVFile
	json.Unmarshal(cmvRaw, &cmvFile)

	for i, v := range cmvFile.Vectors {
		var result string
		if v.Formula == "ecticket" {
			result = calcEcticketCMV(v.HashKey, v.HashIV, v.PlaintextJSON)
		} else {
			result = calcCheckMacValue(v.HashKey, v.HashIV, v.Params, v.Method)
		}
		status := "PASS"
		if result != v.Expected {
			status = "FAIL"
			failures++
		}
		fmt.Printf("  Vector %d: %s | %s\n", i+1, status, v.Name)
		if result != v.Expected {
			fmt.Printf("    Expected: %s\n", v.Expected)
			fmt.Printf("    Got:      %s\n", result)
		}

		// Optional: verify the wrong_%20 diagnostic value
		if v.WrongWithPercent20 != "" {
			keys := make([]string, 0, len(v.Params))
			for k := range v.Params {
				keys = append(keys, k)
			}
			sort.Slice(keys, func(a, b int) bool {
				return strings.ToLower(keys[a]) < strings.ToLower(keys[b])
			})
			parts := make([]string, 0, len(v.Params))
			for _, k := range keys {
				parts = append(parts, k+"="+v.Params[k])
			}
			raw := "HashKey=" + v.HashKey + "&" + strings.Join(parts, "&") + "&HashIV=" + v.HashIV
			encodedCorrect := ecpayUrlEncode(raw)
			encodedWrong := strings.ReplaceAll(encodedCorrect, "+", "%20")
			sum := sha256.Sum256([]byte(encodedWrong))
			wrongHash := strings.ToUpper(fmt.Sprintf("%x", sum))
			check("wrong %20", v.WrongWithPercent20, wrongHash)
		}
	}

	// ── AES Vectors ───────────────────────────
	fmt.Println()
	fmt.Println(strings.Repeat("=", 60))
	fmt.Println("AES Encryption/Decryption Vectors")
	fmt.Println(strings.Repeat("=", 60))

	aesRaw, _ := os.ReadFile("test-vectors/aes-encryption.json")
	var aesFile AESFile
	json.Unmarshal(aesRaw, &aesFile)

	for i, v := range aesFile.Vectors {
		if v.Direction == "decrypt" {
			result, err := aesDecrypt(v.EncryptedBase64, v.HashKey, v.HashIV)
			if err != nil {
				failures++
				fmt.Printf("  Vector %d: FAIL | %s (error: %v)\n", i+1, v.Name, err)
				continue
			}
			status := "PASS"
			if result != v.ExpectedDecrypted {
				status = "FAIL"
				failures++
			}
			fmt.Printf("  Vector %d: %s | %s\n", i+1, status, v.Name)
			if result != v.ExpectedDecrypted {
				fmt.Printf("    Expected: %s\n", v.ExpectedDecrypted)
				fmt.Printf("    Got:      %s\n", result)
			}
			// Also verify URL decode → JSON
			urlDecoded, _ := url.QueryUnescape(result)
			check("URL decode -> JSON", v.ExpectedJSON, urlDecoded)
		} else {
			b64, urlEncoded, err := aesEncrypt(v.PlaintextJSON, v.HashKey, v.HashIV)
			if err != nil {
				failures++
				fmt.Printf("  Vector %d: FAIL | %s (error: %v)\n", i+1, v.Name, err)
				continue
			}
			status := "PASS"
			if b64 != v.ExpectedBase64 {
				status = "FAIL"
				failures++
			}
			fmt.Printf("  Vector %d: %s | %s\n", i+1, status, v.Name)
			if b64 != v.ExpectedBase64 {
				fmt.Printf("    Expected: %s\n", v.ExpectedBase64)
				fmt.Printf("    Got:      %s\n", b64)
			}
			if v.ExpectedURLEncoded != "" {
				check("URL encode", v.ExpectedURLEncoded, urlEncoded)
			}
			if v.ExpectedURLEncodedLength > 0 {
				actualLen := len([]byte(urlEncoded))
				checkInt(fmt.Sprintf("URL encode length (%d bytes)", actualLen), v.ExpectedURLEncodedLength, actualLen)
			}
		}
	}

	// ── URL Encode Comparison Vectors ─────────
	fmt.Println()
	fmt.Println(strings.Repeat("=", 60))
	fmt.Println("URL Encode Comparison Vectors")
	fmt.Println(strings.Repeat("=", 60))

	ueRaw, _ := os.ReadFile("test-vectors/url-encode-comparison.json")
	var ueFile UEFile
	json.Unmarshal(ueRaw, &ueFile)

	for i, v := range ueFile.Vectors {
		cmvResult := ecpayUrlEncode(v.Input)
		aesResult := aesUrlEncode(v.Input)
		cmvOK := cmvResult == v.EcpayURLEncode
		aesOK := aesResult == v.AESURLEncode
		status := "PASS"
		if !cmvOK || !aesOK {
			status = "FAIL"
			failures++
		}
		fmt.Printf("  Vector %d: %s | input: %s\n", i+1, status, v.Input)
		if !cmvOK {
			fmt.Printf("    CMV Expected: %s\n", v.EcpayURLEncode)
			fmt.Printf("    CMV Got:      %s\n", cmvResult)
		}
		if !aesOK {
			fmt.Printf("    AES Expected: %s\n", v.AESURLEncode)
			fmt.Printf("    AES Got:      %s\n", aesResult)
		}
	}

	// ── Summary ───────────────────────────────
	fmt.Println()
	fmt.Println(strings.Repeat("=", 60))
	totalCMV := len(cmvFile.Vectors)
	totalAES := len(aesFile.Vectors)
	totalUE := len(ueFile.Vectors)
	fmt.Printf("Total: %d CMV + %d AES + %d URL encode = %d vectors\n",
		totalCMV, totalAES, totalUE, totalCMV+totalAES+totalUE)
	if failures == 0 {
		fmt.Println("ALL PASSED")
	} else {
		fmt.Printf("FAILURES: %d\n", failures)
		os.Exit(1)
	}
}
