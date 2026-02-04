<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once('../../../helper/header.php');
require_once('../../../helper/log_file.php');
require_once('../../../config/write_database.php');

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    http_response_code(405);
    echo json_encode([
        "success" => 0,
        "message" => "Method Not Allowed"
    ]);
    exit;
}

if (!isset($_FILES['excelFile'])) {
    http_response_code(400);
    echo json_encode([
        "success" => 0,
        "message" => "No CSV file uploaded"
    ]);
    exit;
}

$user_id = $_POST['user_id'] ?? null;
$password = password_hash("secure@123", PASSWORD_BCRYPT);
$role_id = $_POST['role_id'] ?? null;
$user_district_code = $_POST['district_code'] ?? null;
$user_role_id = $_POST['user_role_id'] ?? null;

if (empty($user_id)) {
    http_response_code(400);
    echo json_encode([
        "success" => 0,
        "message" => "User ID is required"
    ]);
    exit;
}

if (empty($role_id)) {
    http_response_code(400);
    echo json_encode([
        "success" => 0,
        "message" => "Role ID is required"
    ]);
    exit;
}

// Validate role_id is valid
$valid_roles = [3, 4, 5, 6];
if (!in_array($role_id, $valid_roles)) {
    http_response_code(400);
    echo json_encode([
        "success" => 0,
        "message" => "Invalid Role ID. Must be one of: 3 (DistrictOfficer), 4 (TalukOfficer), 5 (VillageOfficer), 6 (Surveyor)"
    ]);
    exit;
}

try {
    $csvFile = $_FILES['excelFile']['tmp_name'];

    if (!file_exists($csvFile)) {
        http_response_code(400);
        echo json_encode([
            "success" => 0,
            "message" => "Uploaded file not found"
        ]);
        exit;
    }

    // Arrays to store processed data
    $userMappingsArray = [];
    $failed_records = [];
    $processedCount = 0;
    $validationErrors = [];
    $headerValidationErrors = [];

    // Read CSV file
    if (($handle = fopen($csvFile, "r")) !== FALSE) {
        $isFirstRow = true;
        $rowNumber = 0;

        while (($data = fgetcsv($handle, 1000, ",", '"', "\\")) !== FALSE) {
            $rowNumber++;

            // Skip empty rows
            if (count(array_filter($data)) === 0)
                continue;

            if ($isFirstRow) {
                $isFirstRow = false;

                // Validate CSV header for 6-column format
                $expectedHeaders = ['name', 'mobile_number', 'district_code', 'taluk_code', 'village_code', 'pds_id'];
                $headerValid = true;
                $headerErrors = [];

                for ($i = 0; $i < min(count($data), count($expectedHeaders)); $i++) {
                    if (strtolower(trim($data[$i])) !== $expectedHeaders[$i]) {
                        $headerValid = false;
                        $headerErrors[] = "Column " . ($i + 1) . ": Expected '" . $expectedHeaders[$i] . "', found '" . trim($data[$i]) . "'";
                    }
                }

                if (!$headerValid) {
                    $headerValidationErrors = $headerErrors;
                }

                continue; // Skip header row
            }

            // Store original row data for failed records
            $originalRow = $data;
            $rowHasError = false;
            $rowErrorMsg = "";

            // Validate we have exactly 6 columns
            if (count($data) < 6) {
                $rowErrorMsg = "Insufficient columns (expected 6, got " . count($data) . ")";
                $validationErrors[] = "Row " . $rowNumber . ": " . $rowErrorMsg;
                $failed_records[] = createFailedRecord($originalRow, $rowErrorMsg, $rowNumber);
                continue; // Skip this row entirely
            }

            // Extract and sanitize data according to your 6-column CSV structure
            $username = trim($data[0]);
            $mobileno = trim($data[1]);
            $mapping_district_id = trim($data[2]);
            $mapping_taluk_id = trim($data[3]);
            $mapping_village_id = trim($data[4]);
            $mapping_pds_id = trim($data[5]);

            // Validate required fields
            if (empty($username) || empty($mobileno) || empty($mapping_district_id)) {
                $rowErrorMsg = "Missing required fields (name, mobile_number, or district_code)";
                $validationErrors[] = "Row " . $rowNumber . ": " . $rowErrorMsg;
                $failed_records[] = createFailedRecord($originalRow, $rowErrorMsg, $rowNumber);
                continue; // Skip this row entirely
            }

            // Validate special characters in all fields
            $specialCharError = validateSpecialCharacters([
                'name' => $username,
                'mobile_number' => $mobileno,
                'district_code' => $mapping_district_id,
                'taluk_code' => $mapping_taluk_id,
                'village_code' => $mapping_village_id,
                'pds_id' => $mapping_pds_id
            ], $rowNumber);

            if ($specialCharError !== null) {
                $validationErrors[] = $specialCharError;
                $failed_records[] = createFailedRecord($originalRow, $specialCharError, $rowNumber);
                continue; // Skip this row entirely
            }

            // Validate mobile number format
            if (!preg_match('/^[6-9]\d{9}$/', $mobileno)) {
                $rowErrorMsg = "Invalid mobile number format for " . $mobileno;
                $validationErrors[] = "Row " . $rowNumber . ": " . $rowErrorMsg;
                $failed_records[] = createFailedRecord($originalRow, $rowErrorMsg, $rowNumber);
                continue; // Skip this row entirely
            }

            // Validate numeric IDs
            if (!is_numeric($mapping_district_id) || $mapping_district_id <= 0) {
                $rowErrorMsg = "Invalid district ID";
                $validationErrors[] = "Row " . $rowNumber . ": " . $rowErrorMsg;
                $failed_records[] = createFailedRecord($originalRow, $rowErrorMsg, $rowNumber);
                continue; // Skip this row entirely
            }

            // Validate based on role_id
            $roleValidationError = validateRoleBasedFields($role_id, $mapping_taluk_id, $mapping_village_id, $mapping_pds_id, $rowNumber);

            if ($roleValidationError !== null) {
                $validationErrors[] = $roleValidationError;
                $failed_records[] = createFailedRecord($originalRow, $roleValidationError, $rowNumber);
                continue; // Skip this row entirely
            }

            // District validation for EDM role
            if ($mapping_district_id != $user_district_code) {
                $rowErrorMsg = "District code mismatch.";
                $validationErrors[] = "Row " . $rowNumber . ": " . $rowErrorMsg;
                $failed_records[] = createFailedRecord($originalRow, $rowErrorMsg, $rowNumber);
                continue; // Skip this row entirely
            }

            // If we reach here, the row is valid
            // Prepare user mapping data object - match stored procedure expectations
            $userMapping = [
                "username" => $username,
                "mobileno" => $mobileno,
                "district_id" => $mapping_district_id,
                "taluk_id" => !empty($mapping_taluk_id) ? $mapping_taluk_id : "0",
                "village_id" => !empty($mapping_village_id) ? $mapping_village_id : "0",
                "pds_id" => !empty($mapping_pds_id) ? $mapping_pds_id : "0",
                "password" => $password
            ];

            $userMappingsArray[] = $userMapping;
            $processedCount++;
        }

        fclose($handle);
    } else {
        http_response_code(400);
        echo json_encode([
            "success" => 0,
            "message" => "Failed to read uploaded file"
        ]);
        exit;
    }

    // If we have header validation errors, return early (header errors affect all rows)
    if (!empty($headerValidationErrors)) {
        http_response_code(200);
        echo json_encode([
            "success" => 0,
            "message" => "CSV header validation failed",
            "validation_errors" => array_merge($headerValidationErrors, $validationErrors),
            "failed_records" => $failed_records,
            "records_processed" => $processedCount,
            "records_successful" => 0,
            "records_failed" => count($failed_records) + $processedCount // All rows failed due to header error
        ]);
        exit;
    }

    // If no valid records after validation, return with validation errors
    if (empty($userMappingsArray)) {
        http_response_code(200);
        echo json_encode([
            "success" => 0,
            "message" => "No valid user data found in the file after validation",
            "validation_errors" => $validationErrors,
            "failed_records" => $failed_records,
            "records_processed" => $processedCount + count($failed_records),
            "records_successful" => 0,
            "records_failed" => count($failed_records)
        ]);
        exit;
    }

    // Convert to JSON for stored procedure
    $userMappingsJson = json_encode($userMappingsArray);

    // DEBUG: Log the data being sent
    error_log("User Mapping CSV - Processed $processedCount records, sending " . count($userMappingsArray) . " valid records");

    // Call the stored procedure
    $sql = "CALL public.sp_process_user_mapping_from_json(:p_input, :userid, :p_password, :p_role, :result_json)";
    $stmt = $write_db->prepare($sql);

    // Bind input parameters
    $stmt->bindParam(':p_input', $userMappingsJson, PDO::PARAM_STR);
    $stmt->bindParam(':userid', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':p_password', $password, PDO::PARAM_STR);
    $stmt->bindParam(':p_role', $role_id, PDO::PARAM_INT);

    // Bind output parameter
    $result_json = null;
    $stmt->bindParam(':result_json', $result_json, PDO::PARAM_STR, 10000);

    $stmt->execute();

    // Fetch the results
    $result_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $result_json = $result_data[0]['result_json'] ?? null;

    if (!$result_json) {
        throw new Exception("No result returned from stored procedure");
    }

    // Process the result JSON
    $processingResults = json_decode($result_json, true) ?? [];

    // Analyze results
    $successCount = 0;
    $failedCount = 0;
    $procedureFailedRecords = [];

    if (is_array($processingResults)) {
        foreach ($processingResults as $result) {
            $error = trim($result['errorremarks'] ?? '');
            if ($error === '') {
                $successCount++;
            } else {
                $failedCount++;
                // Add to procedure failed records
                $failedRecord = findFailedMappingRecord($userMappingsArray, $result, $procedureFailedRecords);
                if ($failedRecord && !in_array($failedRecord, $procedureFailedRecords, true)) {
                    $procedureFailedRecords[] = $failedRecord;
                }
            }
        }
    }

    // Combine all failed records (validation + procedure failures)
    $allFailedRecords = array_merge($failed_records, $procedureFailedRecords);

    // Calculate total records (valid + invalid)
    $totalRecords = $processedCount + count($failed_records);

    // Determine overall success based on successful records
    $overallSuccess = ($successCount > 0);

    // Prepare response
    $response = [
        "success" => $overallSuccess ? 1 : 0,
        "message" => $overallSuccess ?
            "Bulk upload completed with " . $successCount . " successful and " . count($allFailedRecords) . " failed records" :
            "Bulk upload failed - no records were processed successfully",
        "records_total" => $totalRecords,
        "records_validation_failed" => count($failed_records),
        "records_procedure_failed" => $failedCount,
        "records_successful" => $successCount,
        "validation_errors" => $validationErrors,
        "failed_records" => $allFailedRecords
    ];

    http_response_code(200);
    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(400);
    echo json_encode([
        "success" => 0,
        "message" => "Database Error: " . $e->getMessage(),
        "error_details" => $e->getTraceAsString()
    ]);
    exit;
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        "success" => 0,
        "message" => "Error: " . $e->getMessage()
    ]);
    exit;
} finally {
    $write_db = null;
}

/**
 * Validate fields based on role_id
 */
function validateRoleBasedFields($role_id, $taluk_id, $village_id, $pds_id, $rowNumber)
{
    switch ($role_id) {
        case 3: // DistrictOfficer
            if (!empty($taluk_id) && $taluk_id != "0") {
                return "Row " . $rowNumber . ": For District Officer, taluk_code must be 0";
            }
            if (!empty($village_id) && $village_id != "0") {
                return "Row " . $rowNumber . ": For District Officer, village_code must be 0";
            }
            if (!empty($pds_id) && $pds_id != "0") {
                return "Row " . $rowNumber . ": For District Officer, pds_id must be 0";
            }
            break;

        case 4: // TalukOfficer
            if (empty($taluk_id) || $taluk_id == "0") {
                return "Row " . $rowNumber . ": For Taluk Officer, taluk_code is mandatory and cannot be 0";
            }
            if (!empty($village_id) && $village_id != "0") {
                return "Row " . $rowNumber . ": For Taluk Officer, village_code must be 0";
            }
            if (!empty($pds_id) && $pds_id != "0") {
                return "Row " . $rowNumber . ": For Taluk Officer, pds_id must be 0";
            }
            break;

        case 5: // VillageOfficer
            if (empty($taluk_id) || $taluk_id == "0") {
                return "Row " . $rowNumber . ": For Village Officer, taluk_code is mandatory and cannot be 0";
            }
            if (empty($village_id) || $village_id == "0") {
                return "Row " . $rowNumber . ": For Village Officer, village_code is mandatory and cannot be 0";
            }
            if (!empty($pds_id) && $pds_id != "0") {
                return "Row " . $rowNumber . ": For Village Officer, pds_id must be 0";
            }
            break;

        case 6: // Surveyor
            if (empty($taluk_id) || $taluk_id == "0") {
                return "Row " . $rowNumber . ": For Surveyor, taluk_code is mandatory and cannot be 0";
            }
            if (empty($village_id) || $village_id == "0") {
                return "Row " . $rowNumber . ": For Surveyor, village_code is mandatory and cannot be 0";
            }
            if (empty($pds_id) || $pds_id == "0") {
                return "Row " . $rowNumber . ": For Surveyor, pds_id is mandatory and cannot be 0";
            }
            break;
    }

    // Additional numeric validation for non-zero fields
    if (!empty($taluk_id) && $taluk_id != "0" && (!is_numeric($taluk_id) || $taluk_id <= 0)) {
        return "Row " . $rowNumber . ": Invalid taluk ID";
    }

    if (!empty($village_id) && $village_id != "0" && (!is_numeric($village_id) || $village_id <= 0)) {
        return "Row " . $rowNumber . ": Invalid village ID";
    }

    if (!empty($pds_id) && $pds_id != "0" && (!is_numeric($pds_id) || $pds_id <= 0)) {
        return "Row " . $rowNumber . ": Invalid PDS ID";
    }

    return null; // No validation errors
}

/**
 * Validate special characters in all fields
 */
function validateSpecialCharacters($fields, $rowNumber)
{
    // Define allowed patterns for each field type
    $patterns = [
        'name' => '/^[a-zA-Z\s\.\-]+$/u', // Letters, spaces, dots, hyphens
        'mobile_number' => '/^[0-9]+$/', // Only digits
        'district_code' => '/^[0-9]+$/', // Only digits
        'taluk_code' => '/^[0-9]*$/', // Digits or empty
        'village_code' => '/^[0-9]*$/', // Digits or empty
        'pds_id' => '/^[0-9]*$/' // Digits or empty
    ];

    // Define field names for error messages
    $fieldNames = [
        'name' => 'Name',
        'mobile_number' => 'Mobile Number',
        'district_code' => 'District Code',
        'taluk_code' => 'Taluk Code',
        'village_code' => 'Village Code',
        'pds_id' => 'PDS ID'
    ];

    foreach ($fields as $fieldName => $value) {
        // Skip empty values for optional fields (except name and mobile)
        if (empty($value) && in_array($fieldName, ['taluk_code', 'village_code', 'pds_id'])) {
            continue;
        }

        // Check if value matches the allowed pattern
        if (!preg_match($patterns[$fieldName], $value)) {
            // Determine which characters are not allowed
            $invalidChars = findInvalidCharacters($value, $fieldName);

            return "Row " . $rowNumber . ": " . $fieldNames[$fieldName] .
                " contains invalid characters. " .
                ($invalidChars ? "Invalid character(s): " . implode(', ', $invalidChars) :
                    "Only " . getAllowedCharactersDescription($fieldName) . " are allowed.");
        }
    }

    return null; // No special character errors
}

/**
 * Find invalid characters in a string
 */
function findInvalidCharacters($string, $fieldName)
{
    // Define allowed character sets for each field
    $allowedSets = [
        'name' => 'a-zA-Z\s\.\-',
        'mobile_number' => '0-9',
        'district_code' => '0-9',
        'taluk_code' => '0-9',
        'village_code' => '0-9',
        'pds_id' => '0-9'
    ];

    $invalidChars = [];
    $allowedPattern = '/[' . $allowedSets[$fieldName] . ']/u';

    // Split string into characters and check each one
    $chars = preg_split('//u', $string, -1, PREG_SPLIT_NO_EMPTY);
    foreach ($chars as $char) {
        if (!preg_match($allowedPattern, $char)) {
            // Add character if not already in array
            $charDisplay = ($char === ' ') ? 'space' : $char;
            if (!in_array($charDisplay, $invalidChars)) {
                $invalidChars[] = $charDisplay;
            }
        }
    }

    return $invalidChars;
}

/**
 * Get description of allowed characters for a field
 */
function getAllowedCharactersDescription($fieldName)
{
    $descriptions = [
        'name' => 'letters, spaces, dots (.), and hyphens (-)',
        'mobile_number' => 'digits (0-9)',
        'district_code' => 'digits (0-9)',
        'taluk_code' => 'digits (0-9)',
        'village_code' => 'digits (0-9)',
        'pds_id' => 'digits (0-9)'
    ];

    return $descriptions[$fieldName] ?? 'valid characters';
}

/**
 * Create failed record object for download
 */
function createFailedRecord($originalRow, $errorReason, $rowNumber)
{
    return [
        "row_number" => $rowNumber,
        "name" => isset($originalRow[0]) ? trim($originalRow[0]) : '',
        "mobile_number" => isset($originalRow[1]) ? trim($originalRow[1]) : '',
        "district_code" => isset($originalRow[2]) ? trim($originalRow[2]) : '',
        "taluk_code" => isset($originalRow[3]) ? trim($originalRow[3]) : '',
        "village_code" => isset($originalRow[4]) ? trim($originalRow[4]) : '',
        "pds_id" => isset($originalRow[5]) ? trim($originalRow[5]) : '',
        "error_reason" => $errorReason
    ];
}

/**
 * Find failed mapping record from user data array for procedure errors
 */
function findFailedMappingRecord($userMappingsArray, $procedureResult, $existingFailedRecords)
{
    foreach ($userMappingsArray as $userMapping) {
        // Match based on mobile number
        if ($userMapping['mobileno'] == $procedureResult['mobileno']) {

            // Check if this record is already in failed records
            // $alreadyExists = false;
            // foreach ($existingFailedRecords as $existingRecord) {
            //     if ($existingRecord['mobile_number'] == $procedureResult['mobileno']) {
            //         $alreadyExists = true;
            //         break;
            //     }
            // }

            // if (!$alreadyExists) {
            return [
                "row_number" => $procedureResult['row_no'] + 1 ?? 'N/A',
                "name" => $procedureResult['username'] ?? $userMapping['username'],
                "mobile_number" => $procedureResult['mobileno'],
                "district_code" => $procedureResult['district_id'] ?? $userMapping['district_id'],
                "taluk_code" => $procedureResult['taluk_id'] ?? $userMapping['taluk_id'],
                "village_code" => $procedureResult['village_id'] ?? $userMapping['village_id'],
                "pds_id" => $procedureResult['pds_id'] ?? $userMapping['pds_id'],
                "error_reason" => $procedureResult['errorremarks'] ?? 'Unknown error'
            ];
            // }
        }
    }
    return null;
}
?>