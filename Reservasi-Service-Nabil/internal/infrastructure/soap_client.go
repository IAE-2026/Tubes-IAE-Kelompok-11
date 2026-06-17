package infrastructure

import (
	"bytes"
	"context"
	"encoding/xml"
	"fmt"
	"io"
	"log"
	"net/http"
	"os"
	"strings"
	"time"
)

// AuditResponseEnvelope mendefinisikan struktur XML untuk menangkap tag ReceiptNumber dari respons SOAP
type AuditResponseEnvelope struct {
	XMLName xml.Name `xml:"Envelope"`
	Body    struct {
		AuditResponse struct {
			Status        string `xml:"Status"`
			ReceiptNumber string `xml:"ReceiptNumber"`
		} `xml:"AuditResponse"`
	} `xml:"Body"`
}

// SendAuditLog mengirimkan payload JSON ke server SOAP audit
func SendAuditLog(ctx context.Context, payloadJSON string) (string, error) {
	ssoURL := os.Getenv("SSO_URL")
	if ssoURL == "" {
		return "", fmt.Errorf("SSO_URL belum diset di environment")
	}

	// 1. Ambil M2M Token
	token, err := GetM2MToken(ctx)
	if err != nil {
		return "", fmt.Errorf("gagal mendapatkan M2M token: %w", err)
	}

	// 2. Siapkan SOAP Envelope
	soapEnvelope := fmt.Sprintf(`<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:iae="http://iae.central/audit">
 <soap:Body>
  <iae:AuditRequest>
   <iae:TeamID>TEAM-11</iae:TeamID>
   <iae:ActivityName>BookingCreated</iae:ActivityName>
   <iae:LogContent><![CDATA[%s]]></iae:LogContent>
  </iae:AuditRequest>
 </soap:Body>
</soap:Envelope>`, payloadJSON)

	// 3. Buat HTTP Request
	auditURL := fmt.Sprintf("%s/soap/v1/audit", ssoURL)
	req, err := http.NewRequestWithContext(ctx, "POST", auditURL, bytes.NewBufferString(soapEnvelope))
	if err != nil {
		return "", fmt.Errorf("gagal membuat request SOAP: %w", err)
	}

	req.Header.Set("Content-Type", "text/xml; charset=utf-8")
	req.Header.Set("Authorization", fmt.Sprintf("Bearer %s", token))

	// 4. Eksekusi Request
	client := &http.Client{Timeout: 15 * time.Second}
	resp, err := client.Do(req)
	if err != nil {
		return "", fmt.Errorf("gagal hit SOAP api: %w", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		body, _ := io.ReadAll(resp.Body)
		return "", fmt.Errorf("server SOAP merespon status %d: %s", resp.StatusCode, string(body))
	}

	// 5. Unmarshal Respons XML
	respBytes, err := io.ReadAll(resp.Body)
	if err != nil {
		return "", fmt.Errorf("gagal membaca body respons SOAP: %w", err)
	}

	var envelope AuditResponseEnvelope
	if err := xml.Unmarshal(respBytes, &envelope); err != nil || envelope.Body.AuditResponse.ReceiptNumber == "" {
		// Fallback: Jika namespace/format struktur membuat unmarshal struct gagal atau kosong,
		// lakukan ekstraksi string secara manual untuk memastikan keandalannya.
		respStr := string(respBytes)
		if strings.Contains(respStr, "<iae:ReceiptNumber>") && strings.Contains(respStr, "</iae:ReceiptNumber>") {
			start := strings.Index(respStr, "<iae:ReceiptNumber>") + len("<iae:ReceiptNumber>")
			end := strings.Index(respStr, "</iae:ReceiptNumber>")
			return respStr[start:end], nil
		}
		
		log.Printf("Gagal parse XML SOAP: %v. Raw Response: %s", err, string(respBytes))
		return "", fmt.Errorf("gagal menemukan tag ReceiptNumber pada respons XML: %w", err)
	}

	return envelope.Body.AuditResponse.ReceiptNumber, nil
}
