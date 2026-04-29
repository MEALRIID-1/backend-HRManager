# HRManager CRUD API Tests - PowerShell Script
# Script pour tester les routes CRUD API sans Postman

param(
    [string]$BaseUrl = "http://localhost:8000/api",
    [switch]$Verbose = $false
)

# Couleurs pour le output
$Colors = @{
    Success = "Green"
    Error = "Red"
    Warning = "Yellow"
    Info = "Cyan"
}

function Write-Log {
    param([string]$Message, [string]$Type = "Info")
    Write-Host $Message -ForegroundColor $Colors[$Type]
}

function Test-Endpoint {
    param(
        [string]$Method,
        [string]$Uri,
        [hashtable]$Headers = @{},
        [string]$Body = $null,
        [int[]]$ExpectedStatus = @(200),
        [string]$Description = ""
    )
    
    try {
        $params = @{
            Method = $Method
            Uri = $Uri
            Headers = $Headers
            ContentType = "application/json"
        }
        
        if ($Body) { $params.Body = $Body }
        
        $response = Invoke-RestMethod @params
        $statusCode = if ($null -eq $response.StatusCode) { 200 } else { $response.StatusCode }
        
        if ($statusCode -in $ExpectedStatus) {
            Write-Log "✓ [$Method] $Uri - Status $statusCode" "Success"
            if ($Verbose) { Write-Host ($response | ConvertTo-Json -Depth 3) }
            return @{ Success = $true; Response = $response; Status = $statusCode }
        } else {
            Write-Log "✗ [$Method] $Uri - Expected $ExpectedStatus, got $statusCode" "Error"
            return @{ Success = $false; Response = $response; Status = $statusCode }
        }
    }
    catch {
        $statusCode = $_.Exception.Response.StatusCode.Value__
        Write-Log "✗ [$Method] $Uri - $($_.Exception.Message)" "Error"
        if ($statusCode) {
            try {
                $error_body = $_.Exception.Response.Content.ToString() | ConvertFrom-Json
                if ($Verbose) { Write-Host ($error_body | ConvertTo-Json) }
            } catch { }
        }
        return @{ Success = $false; Status = $statusCode; Error = $_.Exception.Message }
    }
}

# ============================================================================
# START TESTS
# ============================================================================

Write-Host "╔════════════════════════════════════════════════════════════════╗"
Write-Host "║       HRManager CRUD API Tests - PowerShell Script            ║"
Write-Host "║       Base URL: $BaseUrl"
Write-Host "╚════════════════════════════════════════════════════════════════╝"
Write-Host ""

$results = @{ Passed = 0; Failed = 0; Total = 0 }
$tokens = @{}

# ============================================================================
# 1. HEALTH CHECK
# ============================================================================
Write-Log "► Testing Health Endpoint" "Info"
$test = Test-Endpoint -Method "GET" -Uri "$BaseUrl/health" -ExpectedStatus @(200, 503)
$results.Total++
if ($test.Success) { $results.Passed++ } else { $results.Failed++ }
Write-Host ""

# ============================================================================
# 2. AUTHENTICATION
# ============================================================================
Write-Log "► Testing Authentication" "Info"

$adminLogin = @{
    email = "admin@hrmanager.com"
    password = "password123"
} | ConvertTo-Json

$test = Test-Endpoint -Method "POST" -Uri "$BaseUrl/login" -Body $adminLogin -ExpectedStatus @(200, 401, 422)
$results.Total++

if ($test.Success -and $test.Response.data.token) {
    $results.Passed++
    $tokens.admin = $test.Response.data.token
    Write-Log "  Token Admin: $($tokens.admin.Substring(0, 20))..." "Info"
} else {
    $results.Failed++
    Write-Log "  Could not retrieve admin token" "Warning"
}

$rhLogin = @{
    email = "rh@hrmanager.com"
    password = "password123"
} | ConvertTo-Json

$test = Test-Endpoint -Method "POST" -Uri "$BaseUrl/login" -Body $rhLogin -ExpectedStatus @(200, 401, 422)
$results.Total++

if ($test.Success -and $test.Response.data.token) {
    $results.Passed++
    $tokens.rh = $test.Response.data.token
    Write-Log "  Token RH: $($tokens.rh.Substring(0, 20))..." "Info"
} else {
    $results.Failed++
}
Write-Host ""

# ============================================================================
# 3. EMPLOYEES CRUD
# ============================================================================
if ($tokens.admin) {
    Write-Log "► Testing Employees CRUD" "Info"
    
    $headers = @{ Authorization = "Bearer $($tokens.admin)" }
    
    # CREATE Employee
    $employeeData = @{
        nom = "TestUser"
        prenom = "CRUD"
        email = "test.crud@hrmanager.com"
        telephone = "0123456789"
        date_embauche = "2024-01-01"
        matricule = "EMP-TEST-001"
        genre = "MASCULIN"
        posteId = 1
        departementId = 1
        typeContrat = "CDI"
        salaireBase = 2500
    } | ConvertTo-Json
    
    $test = Test-Endpoint -Method "POST" -Uri "$BaseUrl/employees" -Body $employeeData -Headers $headers -ExpectedStatus @(200, 201, 403, 422)
    $results.Total++
    
    $employee_id = $null
    if ($test.Success -and $test.Response.data.id) {
        $results.Passed++
        $employee_id = $test.Response.data.id
        Write-Log "  Created Employee ID: $employee_id" "Info"
    } else {
        $results.Failed++
    }
    
    # READ Employees List
    $test = Test-Endpoint -Method "GET" -Uri "$BaseUrl/employees" -Headers $headers -ExpectedStatus @(200, 403)
    $results.Total++
    if ($test.Success) { $results.Passed++ } else { $results.Failed++ }
    
    # READ Employee by ID
    if ($employee_id) {
        $test = Test-Endpoint -Method "GET" -Uri "$BaseUrl/employees/$employee_id" -Headers $headers -ExpectedStatus @(200, 404, 403)
        $results.Total++
        if ($test.Success) { $results.Passed++ } else { $results.Failed++ }
        
        # UPDATE Employee
        $updateData = @{
            nom = "TestUserUpdated"
            email = "test.crud.updated@hrmanager.com"
        } | ConvertTo-Json
        
        $test = Test-Endpoint -Method "PUT" -Uri "$BaseUrl/employees/$employee_id" -Body $updateData -Headers $headers -ExpectedStatus @(200, 404, 403, 422)
        $results.Total++
        if ($test.Success) { $results.Passed++ } else { $results.Failed++ }
        
        # DELETE Employee
        $test = Test-Endpoint -Method "DELETE" -Uri "$BaseUrl/employees/$employee_id" -Headers $headers -ExpectedStatus @(200, 404, 403)
        $results.Total++
        if ($test.Success) { $results.Passed++ } else { $results.Failed++ }
    }
    
    # Employee Stats
    $test = Test-Endpoint -Method "GET" -Uri "$BaseUrl/employees/stats" -Headers $headers -ExpectedStatus @(200, 403)
    $results.Total++
    if ($test.Success) { $results.Passed++ } else { $results.Failed++ }
    
    Write-Host ""
}

# ============================================================================
# 4. CONTRACTS CRUD
# ============================================================================
if ($tokens.admin) {
    Write-Log "► Testing Contracts CRUD" "Info"
    
    $headers = @{ Authorization = "Bearer $($tokens.admin)" }
    
    # CREATE Contract
    $contractData = @{
        employeeId = 1
        type = "CDI"
        dateDebut = "2024-01-01"
        dateFin = "2026-01-01"
        salaire = 2500
        statut = "ACTIF"
    } | ConvertTo-Json
    
    $test = Test-Endpoint -Method "POST" -Uri "$BaseUrl/contracts" -Body $contractData -Headers $headers -ExpectedStatus @(200, 201, 403, 422)
    $results.Total++
    
    $contract_id = $null
    if ($test.Success -and $test.Response.data.id) {
        $results.Passed++
        $contract_id = $test.Response.data.id
        Write-Log "  Created Contract ID: $contract_id" "Info"
    } else {
        $results.Failed++
    }
    
    # READ Contracts List
    $test = Test-Endpoint -Method "GET" -Uri "$BaseUrl/contracts" -Headers $headers -ExpectedStatus @(200, 403)
    $results.Total++
    if ($test.Success) { $results.Passed++ } else { $results.Failed++ }
    
    # READ Contract by ID
    if ($contract_id) {
        $test = Test-Endpoint -Method "GET" -Uri "$BaseUrl/contracts/$contract_id" -Headers $headers -ExpectedStatus @(200, 404, 403)
        $results.Total++
        if ($test.Success) { $results.Passed++ } else { $results.Failed++ }
        
        # UPDATE Contract
        $updateData = @{ salaire = 2700 } | ConvertTo-Json
        $test = Test-Endpoint -Method "PUT" -Uri "$BaseUrl/contracts/$contract_id" -Body $updateData -Headers $headers -ExpectedStatus @(200, 404, 403, 422)
        $results.Total++
        if ($test.Success) { $results.Passed++ } else { $results.Failed++ }
        
        # DELETE Contract
        $test = Test-Endpoint -Method "DELETE" -Uri "$BaseUrl/contracts/$contract_id" -Headers $headers -ExpectedStatus @(200, 404, 403)
        $results.Total++
        if ($test.Success) { $results.Passed++ } else { $results.Failed++ }
    }
    
    Write-Host ""
}

# ============================================================================
# 5. PERMISSIONS CRUD
# ============================================================================
if ($tokens.admin) {
    Write-Log "► Testing Permissions CRUD" "Info"
    
    $headers = @{ Authorization = "Bearer $($tokens.admin)" }
    
    # READ Permissions List
    $test = Test-Endpoint -Method "GET" -Uri "$BaseUrl/permissions" -Headers $headers -ExpectedStatus @(200, 403)
    $results.Total++
    if ($test.Success) { $results.Passed++ } else { $results.Failed++ }
    
    # READ Permissions by Module
    $test = Test-Endpoint -Method "GET" -Uri "$BaseUrl/permissions/by-module" -Headers $headers -ExpectedStatus @(200, 403)
    $results.Total++
    if ($test.Success) { $results.Passed++ } else { $results.Failed++ }
    
    Write-Host ""
}

# ============================================================================
# 6. ROLES CRUD
# ============================================================================
if ($tokens.admin) {
    Write-Log "► Testing Roles CRUD" "Info"
    
    $headers = @{ Authorization = "Bearer $($tokens.admin)" }
    
    # READ Roles List
    $test = Test-Endpoint -Method "GET" -Uri "$BaseUrl/roles" -Headers $headers -ExpectedStatus @(200, 403)
    $results.Total++
    if ($test.Success) { $results.Passed++ } else { $results.Failed++ }
    
    Write-Host ""
}

# ============================================================================
# 7. NOTIFICATIONS CRUD
# ============================================================================
if ($tokens.admin) {
    Write-Log "► Testing Notifications CRUD" "Info"
    
    $headers = @{ Authorization = "Bearer $($tokens.admin)" }
    
    # READ Notifications List
    $test = Test-Endpoint -Method "GET" -Uri "$BaseUrl/notifications" -Headers $headers -ExpectedStatus @(200, 401)
    $results.Total++
    if ($test.Success) { $results.Passed++ } else { $results.Failed++ }
    
    # READ Unread Count
    $test = Test-Endpoint -Method "GET" -Uri "$BaseUrl/notifications/unread-count" -Headers $headers -ExpectedStatus @(200, 401)
    $results.Total++
    if ($test.Success) { $results.Passed++ } else { $results.Failed++ }
    
    Write-Host ""
}

# ============================================================================
# SUMMARY
# ============================================================================
Write-Host "╔════════════════════════════════════════════════════════════════╗"
Write-Host "║                        TEST SUMMARY                            ║"
Write-Host "╠════════════════════════════════════════════════════════════════╣"
Write-Log "Total Tests: $($results.Total)" "Info"
Write-Log "Passed: $($results.Passed)" "Success"
Write-Log "Failed: $($results.Failed)" "Error"

$passRate = if ($results.Total -gt 0) { [math]::Round(($results.Passed / $results.Total) * 100, 2) } else { 0 }
Write-Log "Pass Rate: $passRate%" $(if ($passRate -ge 80) { "Success" } else { "Warning" })

Write-Host "╚════════════════════════════════════════════════════════════════╝"

exit $(if ($results.Failed -eq 0) { 0 } else { 1 })
