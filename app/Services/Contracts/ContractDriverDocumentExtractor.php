<?php

namespace App\Services\Contracts;

use App\Services\ClientDocuments\LocalClientDocumentExtractor;

class ContractDriverDocumentExtractor
{
    public function __construct(
        private LocalClientDocumentExtractor $localClientDocumentExtractor
    ) {
    }

    /**
     * @param  array<int, string>  $tempFolders
     * @return array{
     *   fields: array<string, mixed>,
     *   raw_output: array<string, mixed>,
     *   raw_text: string,
     *   confidence: float|null,
     *   provider: string|null,
     *   engine: string|null
     * }
     */
    public function extractFromTempFolders(array $tempFolders, string $documentType): array
    {
        $result = $this->localClientDocumentExtractor->extractFromTempFolders($tempFolders, $documentType);

        return [
            'fields' => $this->mapFields($result['fields'], $documentType),
            'raw_output' => $result['raw_output'],
            'raw_text' => $result['raw_text'],
            'confidence' => $result['confidence'],
            'provider' => $result['provider'],
            'engine' => $result['engine'],
        ];
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function mapFields(array $fields, string $documentType): array
    {
        $normalized = [];

        $fullName = $this->nullableString($fields['full_name'] ?? null);
        if ($fullName !== null) {
            $normalized['full_name'] = $fullName;
        }

        $nationality = $this->nullableString($fields['nationality'] ?? null);
        if ($nationality !== null) {
            $normalized['nationality'] = $nationality;
        }

        $dateOfBirth = $this->nullableString($fields['date_of_birth'] ?? null);
        if ($dateOfBirth !== null) {
            $normalized['date_of_birth'] = $dateOfBirth;
        }

        $documentNumber = $this->nullableString($fields['document_number'] ?? null);
        $expiryDate = $this->nullableString($fields['expiry_date'] ?? null);

        if ($documentType === 'driver_license') {
            if ($documentNumber !== null) {
                $normalized['license_number'] = $documentNumber;
            }
            if ($expiryDate !== null) {
                $normalized['license_expiry_date'] = $expiryDate;
            }
        } else {
            if ($documentType === 'id_card' && $documentNumber !== null) {
                $normalized['identity_number'] = $documentNumber;
            }

            if ($documentType === 'residency_card' && $documentNumber !== null) {
                $normalized['residency_number'] = $documentNumber;
            }

            if ($expiryDate !== null) {
                $normalized['identity_expiry_date'] = $expiryDate;
            }
        }

        return $normalized;
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }
}
