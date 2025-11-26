# 5 Real-Life Use Cases for Stash/DeadDrop
## Complete with Subscriber/Consumer UX and Revenue Projections

---

## Use Case 1: Philippine Government Housing Assistance (Pag-IBIG/DHSUD)

### Subscriber
**Department of Human Settlements and Urban Development (DHSUD)** - Government agency managing housing programs

### End Users (Consumers)
Filipino families applying for housing assistance, subsidized loans, or relocation programs (typically earning ₱10K-₱50K/month)

### User Experience Flow

**For Citizens:**
1. Visit DHSUD portal at `housing.gov.ph/apply`
2. Embedded Stashlet shows interactive document checklist:
   - ✅ Valid IDs (2 types) - SSS/GSIS + Driver's License/Passport
   - ⏳ Proof of income (3 months pay stubs or ITR)
   - ⏳ Barangay certificate of residency
   - ⏳ Marriage certificate (if applicable)
   - ⏳ Property tax receipts (if homeowner)
3. Click "Upload Valid IDs" → drag-and-drop or mobile camera capture
4. Real-time validation:
   - AI detects ID type: "SSS ID detected ✓"
   - Face matching between two IDs
   - Expiry date check: "Valid until 2027 ✓"
   - Red flag: "ID appears expired" (with human review option)
5. AI extracts data automatically:
   - Full name: "Juan Dela Cruz"
   - Address: "123 Barangay Santolan, Pasig City"
   - Birth date: "1985-06-15"
6. Pre-fills application form - citizen just confirms
7. Upload income documents → AI extracts monthly income: "₱25,000"
8. Checklist updates in real-time: ✅ IDs verified, ✅ Income verified, ⏳ Barangay cert pending
9. SMS notification: "2 documents verified. 1 document still needed."
10. Submit → "Application #2025-HD-00123 received. Expected review: 3-5 days"
11. Status portal shows: 
    - ⏳ Application under review
    - 📊 AI pre-screening: "Eligible - Priority Housing Program"
    - 👤 Assigned to Officer: Maria Santos

**For DHSUD Officers:**
1. Dashboard shows: "1,247 applications today, 89 need review"
2. AI pre-screening results:
   - ✅ 850 auto-eligible (income < ₱30K, complete docs)
   - ⚠️ 89 need review (borderline income, missing documents)
   - ❌ 308 auto-rejected (income too high, incomplete)
3. Click application #2025-HD-00123:
   - AI summary: "Family of 4, ₱25K income, eligible for ₱500K subsidy"
   - Extracted data side-by-side with original documents
   - Risk flags: "None"
   - Recommendation: "Approve - Priority (Score: 87/100)"
4. One-click approve → triggers:
   - SMS to applicant: "Approved! Next steps: Schedule site visit"
   - Email with approval letter PDF
   - Integration with Pag-IBIG loan system
   - Audit log entry with officer ID and timestamp
5. Officer processed 200+ applications in 1 day vs 20 manually

### Technical Pipeline Configuration
```
Upload → Document Classification (ID/Income/Cert) → 
Face Verification (HyperVerge API) → OCR + Data Extraction (GPT-4 Vision) → 
Income Eligibility Check (Rule-based: <₱30K) → 
AI Scoring (Priority factors: family size, location, urgency) → 
Officer Review Queue (if score 50-80) OR Auto-approve (if score >80) → 
Approval Workflow → Notification (SMS via EngageSpark + Email) → 
Integration Trigger (Pag-IBIG API)
```

### Revenue Model
- **Setup Fee**: ₱200,000 one-time (custom pipeline configuration)
- **Base Platform Fee**: ₱80,000/month (unlimited processing)
- **Per-Application Fee**: ₱8 per submission
- **Volume Estimate**: 15,000 applications/month
- **Application Revenue**: ₱120,000/month
- **Third-party API costs** (passed through):
  - HyperVerge KYC: ₱25 per verification
  - SMS notifications: ₱1 per SMS
- **Total Monthly Revenue**: ₱200,000 (₱80K base + ₱120K per-app)
- **Annual Revenue per Agency**: **₱2.4M/year**

### Market Size (Philippines)
- **Government Agencies**: 25+ agencies
  - DHSUD (housing)
  - SSS (social security claims)
  - PhilHealth (healthcare)
  - DSWD (social welfare)
  - BIR (tax document processing)
  - LTO (license applications)
  - PSA (civil registry documents)
  - DOLE (overseas worker processing)
- **Potential Annual Revenue**: ₱2.4M × 25 = **₱60M/year**
- **Stretch Goal**: Provincial LGUs (81 provinces) = **₱194M additional**

---

## Use Case 2: Microfinance Loan Processing (SME Lending)

### Subscriber
**Maya Bank / UnionBank / ASA Philippines / CARD MRI** - Financial institutions offering microloans

### End Users (Consumers)
- Sari-sari store owners needing ₱20K inventory capital
- Tricycle drivers buying second-hand units (₱80K)
- Market vendors expanding stalls (₱50K)
- Home-based businesses (₱10K-₱100K)

### User Experience Flow

**For Borrowers (Mobile-First Experience):**
1. See Facebook ad: "Get ₱50K loan in 1 hour - 2.5% interest"
2. Click → lands on Maya Loan page
3. Stashlet embedded: "Apply now - no paperwork needed"
4. Mobile camera uploads:
   - **Selfie with ID** (AI liveness detection prevents fake photos)
   - **Valid ID** (front and back)
   - **Proof of income**: 
     - If employed: pay slips or company ID
     - If self-employed: bank statement screenshots from banking app
   - **Proof of address**: Utility bill or barangay certificate photo
5. AI magic happens:
   - Face matching: Selfie vs ID photo (99.2% match)
   - ID validation: "Valid SSS ID, not expired"
   - Bank statement analysis:
     - "Average daily balance: ₱8,500"
     - "Monthly deposits: ₱35,000"
     - "Monthly withdrawals: ₱28,000"
     - "Cash flow: Positive ₱7,000/month"
6. Instant credit scoring:
   - Internal AI model: 72/100
   - Credit bureau check (CIBI): No negative records
   - Combined score: 68/100 - "Good"
7. Pre-approval screen:
   - "You're approved for up to ₱50,000!"
   - Interest: 2.5%/month (30% APR)
   - Term options: 3, 6, 12 months
   - Monthly payment: ₱18,200 (6 months)
8. Select amount and term → AI shows:
   - "Your ₱7K monthly cash flow can cover ₱5K payment comfortably"
   - "Recommendation: Borrow ₱30K for 6 months"
9. Accept terms → E-signature (DocuSign)
10. "Loan approved! ₱30,000 will be in your Maya wallet in 2 hours"
11. Push notification when funds arrive
12. Loan tracking: "Payment due: Jan 15 - ₱5,000"

**For Loan Officers:**
1. Dashboard: "Today: 327 applications"
   - 💚 245 auto-approved (₱12.25M disbursed)
   - 🟡 58 need review (borderline scores 50-65)
   - 🔴 24 auto-rejected (score <50 or fraud flags)
2. Review queue sorted by:
   - Highest loan amount first
   - Risk score
   - Time in queue
3. Click application #LA-2025-089234:
   - **AI Summary**:
     - Applicant: Maria Santos, 32, Market vendor
     - Requesting: ₱80,000 for inventory
     - Income: ₱42,000/month (verified via bank)
     - Score: 64/100 (borderline)
   - **Risk Flags**:
     - ⚠️ High debt-to-income ratio: 45% (threshold: 40%)
     - ✅ No delinquency history
     - ⚠️ First-time borrower (no track record)
   - **AI Recommendation**: "Approve ₱50K instead of ₱80K, reduce risk"
4. Officer options:
   - ✅ Approve ₱50K (AI suggestion)
   - ✅ Approve ₱80K (override)
   - 📞 Call applicant for clarification
   - ❌ Reject with reason
5. Approve ₱50K → Auto-disburses to wallet
6. Officer processed 150+ reviews in 4 hours vs 15 manually

### Technical Pipeline Configuration
```
Upload → Face Verification + Liveness Check (HyperVerge) → 
Document Classification (ID/Income/Address) → 
OCR + Data Extraction (GPT-4 Vision for bank statements) → 
Transaction Analysis (Custom AI model: income/expense patterns) → 
Credit Bureau Integration (CIBI API) → 
Credit Scoring Algorithm (ML model trained on 100K+ loans) → 
Risk Assessment (Debt-to-income, stability, fraud indicators) → 
Decision Engine:
  - Auto-approve: Score >70 AND amount <₱50K
  - Manual review: Score 50-70 OR amount >₱50K
  - Auto-reject: Score <50 OR fraud flags
→ E-signature (DocuSign API) → 
Disbursement (Maya/GCash/Bank API) → 
SMS/Push Notification
```

### Revenue Model
- **Setup Fee**: ₱500,000 (ML model training on historical loan data)
- **Monthly Platform Fee**: ₱150,000 (includes hosting, updates)
- **Per-Application Fee**: ₱18 per loan application processed
- **Volume Estimate**: 10,000 applications/month per lender
- **Application Revenue**: ₱180,000/month
- **Success Fee**: 0.5% of approved loan value
  - Average loan: ₱35,000
  - Approval rate: 70% (7,000 approved)
  - Loan volume: ₱245M/month
  - Success fee: ₱1.225M/month
- **Total Monthly Revenue**: ₱1.555M (₱150K + ₱180K + ₱1.225M)
- **Annual Revenue per Lender**: **₱18.66M/year**

### Market Size
- **Rural Banks**: 400+ in Philippines
- **Microfinance Institutions**: 100+
- **Digital Lenders**: 20+ (Maya, GCash, Tala, etc.)
- **Target**: 20 lenders in Year 1
- **Year 1 Revenue Potential**: ₱18.66M × 20 = **₱373M/year**

---

## Use Case 3: Real Estate Document Management (Mortgage Applications)

### Subscriber
**Ayala Land / SM Development / DMCI Homes / Century Properties** - Property developers

**Alternative**: **Real Estate Brokers Network** (PRC-licensed brokers)

### End Users (Consumers)
- First-time homebuyers (25-35 years old, ₱30K-₱100K income)
- OFWs (overseas workers) buying properties remotely
- Upgraders selling old property to buy larger home

### User Experience Flow

**For Homebuyers:**

**Scenario**: Sarah, 28, BPO employee (₱65K/month) wants to buy ₱3.5M condo

1. **Discovery**: Browsing Ayala Land website, sees ₱3.5M condo
2. **CTA Button**: "Check if you qualify - 5 minutes"
3. **Stashlet Embedded Calculator**:
   - "Enter your monthly income: ₱65,000"
   - "Do you have co-borrower? Yes (husband: ₱70,000)"
   - Combined income: ₱135,000
   - AI calculates: "You can afford up to ₱5.8M property"
   - Shows: "₱3.5M condo = ₱24,500/month for 20 years"
4. **Pre-Qualification Flow**: "Upload documents to get pre-qualified"
5. **Document Upload** (Stashlet):
   - Employment certificates (both)
   - Latest 3 pay slips
   - BIR ITR (2 years)
   - Bank statements (6 months)
   - Valid IDs
   - Marriage certificate
6. **AI Processing**:
   - Extracts income: "Sarah: ₱65K, Spouse: ₱70K"
   - Calculates debt-to-income:
     - Existing car loan: ₱12K/month
     - Credit cards: ₱8K/month
     - DTI: (12+8+24.5)/135 = 33% ✅ (threshold: 35%)
   - Credit bureau check: "Clean record"
   - Employment stability: "3 years with current employer ✅"
7. **Mortgage Options Generated**:
   - **Option A - Pag-IBIG**: 6.25%, 30 years, ₱21,500/month
   - **Option B - RCBC**: 7.5%, 20 years, ₱28,000/month
   - **Option C - In-house (Ayala)**: 8%, 15 years, ₱33,500/month
   - AI Recommendation: "Pag-IBIG best - save ₱156K in interest"
8. **Pre-Qualified Letter**:
   - "Congratulations! Pre-qualified for ₱3.5M"
   - PDF certificate with QR code (valid 60 days)
   - "Show this to our sales agent"
9. **Next Steps**:
   - Schedule property viewing
   - Reserve unit (₱50K reservation)
   - Proceed with full application to chosen lender
10. **Document Forwarding**:
    - Sarah chooses Pag-IBIG
    - All uploaded documents sent directly to Pag-IBIG via API
    - No re-uploading needed!
11. **Status Tracking**:
    - Portal shows: "Application with Pag-IBIG - Under review"
    - Notifications: "Pag-IBIG requests: Updated ITR"
    - Upload additional docs through same portal
12. **Approval**:
    - "Loan approved! ₱3.5M at 6.25%"
    - E-signing of documents
    - Turnover schedule
    - Move-in!

**For Developers (Ayala Land Sales Team):**
1. **Lead Management Dashboard**:
   - 500 website visitors today
   - 150 started pre-qualification
   - 80 completed (conversion: 53%)
   - 45 pre-qualified
   - 12 reserved units
2. **Lead Quality Scoring**:
   - 🔥 Hot: Pre-qualified, income 2x payment, viewed 3+ times
   - 🟡 Warm: Pre-qualified, income 1.5x payment
   - ⚪ Cold: Not qualified or incomplete docs
3. **Sales Agent View**:
   - "Sarah Santos: Pre-qualified for ₱5.8M, interested in ₱3.5M condo"
   - "Income: ₱135K combined, DTI: 33%, Score: 82/100"
   - "Documents: Complete ✅"
   - "Financing: Pag-IBIG preferred"
   - **Action**: Call immediately (high-quality lead!)
4. **CRM Integration**:
   - Auto-creates Salesforce lead
   - Tags: "Pre-qualified", "Pag-IBIG", "Budget: 3-4M"
   - Assigns to agent based on territory
5. **Pipeline Tracking**:
   - Week 1: 100 leads, 50 pre-qualified
   - Week 2: 20 reservations
   - Week 3: 15 full applications
   - Week 4: 10 approved, 8 closed sales
6. **ROI Metrics**:
   - Pre-qualification reduces tire-kickers by 60%
   - Sales cycle: 45 days → 30 days (50% faster)
   - Closing rate: 15% → 25% (67% improvement)

### Technical Pipeline Configuration
```
Landing Page → Embedded Calculator (Mortgage package integration!) → 
Document Upload (Stashlet) → 
Identity Verification (face matching) → 
Income Extraction (OCR + GPT-4) → 
Employment Verification (call to employer API or manual) → 
Credit Bureau Check (CIBI/TransUnion API) → 
Mortgage Calculation (Use your existing mortgage package):
  - Calculate loanable amount
  - Generate payment schedules for multiple lenders
  - Compare interest rates and total cost
→ AI Recommendation Engine (best lender based on profile) → 
Pre-Qualification Letter Generation (PDF with QR code) → 
CRM Integration (Salesforce API) → 
Document Vault (S3 storage, encrypted) → 
Lender Integration (Pag-IBIG/Bank APIs) → 
Status Tracking Portal → 
E-Signature (DocuSign) → 
Analytics Dashboard
```

### Revenue Model
- **Setup Fee**: ₱800,000 per developer (custom branding, CRM integration)
- **Monthly Platform Fee**: ₱200,000 (unlimited pre-qualifications)
- **Per-Transaction Fee**: ₱800 per completed mortgage application
- **Success Fee**: 0.3% of property value when sale closes
  - Average property: ₱3.5M
  - Success fee: ₱10,500 per sale
- **Volume Estimate** (per developer):
  - 500 pre-qualifications/month
  - 200 full applications
  - 50 closed sales
- **Monthly Revenue**:
  - Platform: ₱200,000
  - Transactions: ₱800 × 200 = ₱160,000
  - Success fees: ₱10,500 × 50 = ₱525,000
  - **Total: ₱885,000/month**
- **Annual Revenue per Developer**: **₱10.62M/year**

### Market Size
- **Major Developers**: 20+ (Ayala, SM, DMCI, Megaworld, etc.)
- **Mid-tier Developers**: 50+
- **Real Estate Broker Networks**: 100+ (5,000+ brokers each)
- **Year 1 Target**: 10 developers
- **Year 1 Revenue Potential**: ₱10.62M × 10 = **₱106.2M/year**

---

## Use Case 4: Healthcare Claims Processing (HMO/PhilHealth)

### Subscriber
**Maxicare / Medicard / Intellicare / PhilHealth** - Health insurance providers

### End Users (Consumers)
- Hospital patients filing insurance claims
- Outpatient clinic visits
- Prescription drug reimbursements
- Dependents' claims (parents filing for children)

### User Experience Flow

**For Patients (Post-Hospital Discharge):**

**Scenario**: Juan, 35, had appendectomy. Hospital bill: ₱85,000. Maxicare coverage: 70%

1. **Hospital Discharge**: Nurse hands flyer: "File your claim online - get reimbursed in 3 days"
2. **QR Code Scan** → Opens Maxicare Claims Portal
3. **Login**: Maxicare member ID + OTP
4. **Claim Type**: "Inpatient confinement"
5. **Upload Documents** (Stashlet):
   - Official receipts (OR)
   - Statement of account (itemized billing)
   - Medical certificate (diagnosis)
   - Doctor's prescription
   - Lab results / X-rays (if applicable)
   - Discharge summary
6. **AI Processing** (happens in seconds):
   - **Document Classification**:
     - "Official Receipt detected"
     - "Medical Certificate detected"
     - "Lab results detected"
   - **OCR + Data Extraction**:
     - Hospital: "St. Luke's Medical Center"
     - Admission date: "2025-11-20"
     - Discharge date: "2025-11-23"
     - Diagnosis: "Acute appendicitis"
     - ICD-10 Code: K35.8 (auto-extracted)
     - Total amount: ₱85,000
     - Line items:
       - Room (3 days): ₱18,000
       - Surgeon fee: ₱25,000
       - Anesthesia: ₱12,000
       - Lab tests: ₱8,000
       - Medications: ₱15,000
       - OR fee: ₱7,000
   - **Coverage Check**:
     - Policy: "Gold Plan - ₱150K/year limit"
     - Used so far: ₱0
     - Room: Covered up to ₱5,000/day (₱15K total) ✅
     - Surgeon: Covered up to ₱30,000 ✅
     - Total covered: ₱59,500 (70% of ₱85K)
     - Patient pays: ₱25,500
   - **Fraud Detection**:
     - AI flags: "No anomalies"
     - Check: Hospital is accredited ✅
     - Check: Diagnosis matches procedure ✅
     - Check: No duplicate claim ✅
7. **Instant Estimate**:
   - "Your claim: ₱59,500 covered"
   - "Out-of-pocket: ₱25,500"
   - "Estimated reimbursement: 3-5 business days"
8. **Status Tracking**:
   - Day 1: "Claim received and validated ✅"
   - Day 2: "Under review ⏳"
   - Day 3: "Approved ✅ - ₱59,500"
   - Day 4: "Payment processed - check your bank account"
9. **Push Notification**: "₱59,500 deposited to BPI account ending 1234"
10. **Receipt**: PDF with breakdown emailed

**For Claims Processors (Maxicare Back Office):**
1. **Dashboard**: "Today: 5,000 claims submitted"
   - 💚 4,200 auto-adjudicated (84% straight-through processing)
   - 🟡 650 need review (13%)
   - 🔴 150 flagged for fraud investigation (3%)
2. **Auto-Adjudication Rules**:
   - Claim amount < ₱50K AND
   - No fraud flags AND
   - All documents complete AND
   - Diagnosis matches procedure → AUTO-APPROVE
3. **Review Queue** (650 claims):
   - Sorted by amount (highest first)
   - Color-coded by urgency
4. **Click Claim #MC-2025-089456**:
   - Patient: Maria Santos, 45
   - Diagnosis: "Pneumonia"
   - Hospital: "Philippine General Hospital"
   - Amount: ₱125,000
   - **AI Red Flags**:
     - ⚠️ Same patient, 3rd ER visit this month
     - ⚠️ Doctor not in accredited list
     - ⚠️ Medication prescribed doesn't match diagnosis
   - **AI Recommendation**: "Investigate for fraud"
5. **Processor Actions**:
   - Call hospital to verify confinement
   - Check doctor credentials
   - Review prescription vs diagnosis mismatch
   - Escalate to fraud investigation team
6. **Fraud Case**:
   - AI detected: Patient + Doctor collusion
   - Fake confinement (patient never admitted)
   - Claim DENIED
   - Alert: "Flag patient and doctor in system"
7. **Productivity Metrics**:
   - Old way: 50 claims/day per processor
   - With AI: 300 reviews/day per processor
   - 6x productivity increase
   - Error rate: 5% → 0.5% (AI double-checks)

### Technical Pipeline Configuration
```
Upload (via web/mobile/hospital direct API) → 
Document Classification (OR/MedCert/Labs/Discharge) → 
OCR + Data Extraction (GPT-4 Vision for medical documents) → 
Medical Code Extraction:
  - ICD-10 diagnosis codes (AI or manual)
  - CPT procedure codes
  - HCPCS drug codes
→ Policy Lookup (member's coverage limits, exclusions) → 
Coverage Calculation:
  - Check annual limit
  - Check per-item limits (room, surgery, etc.)
  - Apply co-insurance (70/30, 80/20, etc.)
  - Deductibles
→ Fraud Detection ML Model:
  - Duplicate claims
  - Unusual patterns (frequency, amount)
  - Provider reputation
  - Diagnosis-procedure mismatch
  - Geographic anomalies
→ Decision Engine:
  - Auto-approve: <₱50K + no flags
  - Manual review: >₱50K OR any flags
  - Fraud investigation: 2+ red flags
→ Adjudication → 
Payment Processing (bank transfer API) → 
Notification (SMS + Email + Push)
```

### Revenue Model
- **Setup Fee**: ₱2,000,000 (ML model training, integration with hospital systems)
- **Monthly Platform Fee**: ₱500,000 (infrastructure, support)
- **Per-Claim Fee**: ₱12 per claim processed
- **Volume Estimate**: 300,000 claims/month (large HMO)
- **Claim Processing Revenue**: ₱3,600,000/month
- **Fraud Prevention Savings**: ₱5M/month (2% of claims = ₱15M, you catch 1/3 = ₱5M)
  - Performance bonus: 10% of fraud prevented = ₱500,000
- **Total Monthly Revenue**: ₱4.6M (₱500K + ₱3.6M + ₱500K bonus)
- **Annual Revenue per HMO**: **₱55.2M/year**

### Market Size
- **Major HMOs**: 10 (Maxicare, Medicard, Intellicare, etc.)
- **PhilHealth**: Government (100M+ members, but different model)
- **Year 1 Target**: 3 HMOs
- **Year 1 Revenue Potential**: ₱55.2M × 3 = **₱165.6M/year**

### Alternative Model for PhilHealth (Government)
- **Per-claim fee**: ₱5 (discounted for volume)
- **Volume**: 10M claims/year
- **Annual Revenue**: **₱50M/year from PhilHealth alone**

---

## Use Case 5: BPO Document Processing (Outsourced Back Office)

### Subscriber
**Accenture / Concentrix / Teleperformance / TDCX** - BPO companies handling back-office for US/EU clients

### End Users (Consumers)
- US insurance policyholders
- EU bank customers
- Australian government service applicants
- UK healthcare patients

### User Experience Flow

**For End-Customers (US Insurance Policyholder Example):**

**Scenario**: John Smith, Florida, claims homeowner's insurance after Hurricane Ian damage

1. **Initiate Claim**: Calls insurer (State Farm) → Routed to Manila BPO
2. **Claim Number**: "SF-2025-FL-089456" assigned
3. **Email Received**: "Submit damage photos and receipts to claims@statefarm.com"
4. **Email with Attachments**:
   - 10 photos of roof damage
   - Contractor estimate: $15,000
   - Proof of ownership
   - Driver's license (for identity)
5. **Stash Processing** (behind the scenes):
   - Email ingested via IMAP/API
   - Attachments extracted
   - Claim number parsed from email subject
6. **John's Experience**:
   - Auto-reply: "We received your documents. Claim status: Processing"
   - Track at statefarm.com/claims/SF-2025-FL-089456
   - Status updates: "Documents verified" → "Assessment in progress" → "Approved"
   - Timeline: 3 days (vs 2 weeks traditional)
7. **Approval Email**: "Your claim is approved for $14,200. Check mailed."

**For BPO Agents (Manila Center):**

**Traditional Process (Before Stash):**
1. Agent opens 200 emails manually
2. Downloads each attachment (10 mins)
3. Renames files to convention: "ClaimNum_DocumentType_Date.jpg"
4. Opens insurer's system
5. Types claim number
6. Uploads each file one-by-one
7. Fills form manually:
   - Policyholder name
   - Address
   - Claim type
   - Damage description
   - Estimated amount
8. Submits (25 mins per claim)
9. **Total**: 100 claims processed per agent per day

**With Stash (Transformed):**
1. Agent opens Stash dashboard
2. **Inbox Shows**: "200 emails processed automatically"
   - AI extracted all data
   - Documents pre-classified
   - Forms pre-filled
3. Agent's job:
   - **Review pre-filled forms** (AI accuracy: 95%)
   - **Fix AI errors** (5% need correction)
   - **Approve and submit** (2 mins per claim)
4. **Total**: 800 claims processed per agent per day
5. **8x productivity increase**

**Detailed Agent Dashboard:**
1. **Queue**: "200 claims ready for review"
2. **Filters**:
   - High-value (>$50K) - need careful review
   - Low-value (<$5K) - quick approval
   - Flagged by AI - need attention
3. **Click Claim SF-2025-FL-089456**:
   - **AI-Extracted Data**:
     - Name: John Smith ✅
     - Address: 123 Oak St, Tampa, FL ✅
     - Policy #: FL-1234567 ✅
     - Damage type: Roof (Hurricane) ✅
     - Estimate: $15,000 ✅
   - **Documents**:
     - ✅ 10 photos uploaded
     - ✅ Contractor estimate
     - ✅ Proof of ownership
     - ✅ ID verified
   - **AI Quality Check**:
     - ✅ All documents readable
     - ✅ Estimate amount matches photos (damage assessment)
     - ⚠️ Contractor not in approved list → Flag for supervisor
   - **Form Preview**: Pre-filled insurer system form
4. **Agent Actions**:
   - Review AI data: Looks good ✅
   - Check flag: Escalate contractor verification to supervisor
   - Click "Submit to Insurer System"
   - API call sends data to State Farm's system
   - Status: "Submitted ✅"
5. **Next Claim**: Auto-loads in 2 seconds

**For BPO Management:**
1. **Dashboard**: "Manila Center Performance"
   - Agents: 100 active
   - Claims processed today: 25,000 (vs 10,000 before)
   - Avg handling time: 2.5 mins (vs 25 mins)
   - Accuracy: 98% (AI + human review)
   - Client SLA: 24-hour processing ✅ (beating 72-hour SLA)
2. **Cost Savings**:
   - Agents needed: 40 (vs 100 before)
   - 60% headcount reduction
   - Savings: $500K/month
3. **Revenue Share**:
   - Stash fee: 30% of savings = $150K/month
4. **Client Satisfaction**:
   - State Farm NPS: 65 → 85 (20-point increase)
   - Renewal rate: 95% → 98%

### Technical Pipeline Configuration
```
Ingestion (Email IMAP/SFTP/API/Web Portal) → 
Email Parsing:
  - Extract claim number from subject
  - Parse sender identity
  - Extract body text (claim narrative)
→ Attachment Extraction → 
Document Classification (ML model):
  - Damage photos
  - Receipts
  - Estimates
  - Identity documents
  - Police reports
  - Medical records
→ OCR + Data Extraction (GPT-4 Vision):
  - Contractor estimates: amount, line items
  - Receipts: dates, amounts, vendors
  - IDs: name, address, DOB
  - Policy numbers
→ Image Analysis (for damage photos):
  - Damage severity score (1-10)
  - Damage type classification
  - Estimate validation
→ Form Pre-filling:
  - Map extracted data to insurer's form fields
  - Validate required fields complete
→ Quality Assurance:
  - AI confidence scores
  - Flag low-confidence extractions
  - Flag anomalies (amount too high, missing docs)
→ Agent Review Interface (Web UI) → 
Human Approval/Correction → 
API Integration (submit to client system):
  - State Farm API
  - Nationwide API
  - Progressive API
  - Etc.
→ Status Update → 
Analytics Dashboard
```

### Revenue Model

**Model 1: Per-Document Pricing**
- **Per-document fee**: ₱2.50 per document processed
- **Volume**: 1,000,000 documents/month (large BPO contract)
- **Monthly Revenue**: ₱2,500,000
- **Annual Revenue**: **₱30M/year**

**Model 2: Revenue Share (Preferred for large clients)**
- **BPO Cost Savings**: ₱25M/month (60% headcount reduction)
- **Revenue Share**: 30% of savings
- **Monthly Revenue**: ₱7,500,000
- **Annual Revenue**: **₱90M/year**

**Model 3: Per-Agent License**
- **License per agent**: ₱5,000/month
- **Agents using Stash**: 500 agents
- **Monthly Revenue**: ₱2,500,000
- **Annual Revenue**: **₱30M/year**

**Actual Pricing Strategy** (Hybrid):
- Base platform fee: ₱500,000/month
- Per-document: ₱1 (for predictable revenue)
- Performance bonus: 10% of client cost savings
- **Estimated Monthly Revenue**: ₱3M - ₱8M depending on volume
- **Annual Revenue per BPO**: **₱36M - ₱96M/year**

### Market Size
- **Large BPOs in Philippines**: 50+ companies
- **Total BPO headcount**: 1.3 million workers
- **Document processing roles**: ~200,000 workers
- **Target**: 10 BPO contracts in Year 1
- **Year 1 Revenue Potential**: ₱50M × 10 = **₱500M/year**

### Key BPO Verticals
1. **Insurance Claims**: 30% of market
2. **Loan Processing**: 25%
3. **Healthcare Claims**: 20%
4. **Government Forms**: 15%
5. **Legal Discovery**: 10%

---

## Revenue Summary Across All 5 Use Cases

| Use Case | Revenue per Customer | Year 1 Customers | Year 1 Revenue | Year 3 Potential |
|----------|---------------------|------------------|----------------|------------------|
| **1. Government Housing** | ₱2.4M/year | 5 agencies | ₱12M | ₱60M (25 agencies) |
| **2. Microfinance Loans** | ₱18.7M/year | 5 lenders | ₱93.5M | ₱373M (20 lenders) |
| **3. Real Estate Mortgage** | ₱10.6M/year | 10 developers | ₱106M | ₱318M (30 developers) |
| **4. Healthcare Claims** | ₱55.2M/year | 3 HMOs | ₱165.6M | ₱552M (10 HMOs) |
| **5. BPO Document Processing** | ₱50M/year | 3 contracts | ₱150M | ₱500M (10 contracts) |
| **TOTAL** | | **26 customers** | **₱527.1M** | **₱1.803B** |

### Conservative Revenue Projections

**Year 1 (MVP + Early Adoption):**
- 26 paying customers across 5 verticals
- **Total Revenue: ₱527.1M**
- Gross margin: 75% (₱395M)
- Net margin: 25% (₱132M)

**Year 2 (Scale + Expansion):**
- 3x customer growth (78 customers)
- Upsells + feature expansion
- **Total Revenue: ₱1.2B**

**Year 3 (Market Leadership):**
- Regional expansion (Indonesia, Vietnam, Thailand)
- Enterprise features (air-gapped, multi-AI)
- **Total Revenue: ₱2.5B+**

---

## Why These Use Cases Are Perfect for Stash/DeadDrop

### Technical Alignment
Every use case leverages your core platform features:

✅ **Multi-Tenancy** - Each customer is isolated subscriber
✅ **Pipeline Processing** - Document → Extract → Validate → Route → Act
✅ **AI Routing** - Different AI models for different tasks
✅ **Credential Management** - Each client uses own KYC/AI/API credentials
✅ **Stashlets** - Embeddable upload widgets
✅ **Queue Abstraction** - Handle millions of documents
✅ **Checklist Engine** - Track document completion status
✅ **Agent Runtime** - AI makes decisions within guardrails

### Business Model Alignment

✅ **High Volume** - Millions of documents per month
✅ **Recurring Revenue** - Monthly platform fees + usage
✅ **Scalable** - No linear cost increase with volume
✅ **Sticky** - Integration creates lock-in (good kind!)
✅ **Upsell Opportunities** - Start basic, expand features
✅ **Network Effects** - More processors = more value

### Market Timing

✅ **Digital Transformation Push** - Post-pandemic acceleration
✅ **Labor Cost Pressure** - BPOs need productivity gains
✅ **Regulatory Compliance** - Government digitization mandates
✅ **AI Hype Cycle** - Market ready to adopt AI solutions
✅ **Philippine Advantage** - BPO capital + tech talent

---

## Meta-Campaign Relevance

The Meta-Campaign becomes even more powerful with real customers:

### Use Case-Driven Evolution

1. **Customer Request**: "Can you extract data from handwritten forms?"
2. **Meta-Campaign**:
   - Analyzes request → "Need handwriting OCR processor"
   - Generates new processor: `HandwritingOcrProcessor`
   - Integrates Google Cloud Vision API
   - Generates tests
   - Creates PR
   - Deploys to staging
   - Customer tests → Approves → Production
3. **Timeline**: 3 days (vs 3 weeks manual)

### Self-Improving Accuracy

1. **AI Model Drift**: Extraction accuracy drops from 95% → 90%
2. **Meta-Campaign Monitors**:
   - Detects accuracy decline
   - Triggers retraining job
   - Generates fine-tuning code
   - Uses recent customer data (with permission)
   - Tests on holdout set
   - Deploys improved model
3. **Result**: Accuracy restored to 96%

### Processor Marketplace

Meta-Campaign can generate new processors requested by customers:
- "Generate Passport OCR processor"
- "Create Tax Form 2316 extractor"
- "Build Prescription validation processor"

Each becomes a reusable component for other customers.

---

## Next Steps

With these use cases validated, you should:

1. **Pick One Vertical** to start (Recommendation: Microfinance - fastest time-to-revenue)
2. **Build MVP** focused on that vertical's specific needs
3. **Get Design Partner** (1-2 paying beta customers)
4. **Iterate Based on Real Usage**
5. **Expand to Adjacent Verticals**

The path to ₱500M+ revenue is clear! 🚀
