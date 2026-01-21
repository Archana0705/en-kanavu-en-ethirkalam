//do not touch
// total source and validation for ekee form



$(document).ready(function () {
  checkExistingSession();


  initOTPFunctionality();

})
function showOTPModal() {
  document.getElementById('otpModal').classList.add('show');
}

function hideOTPModal() {
  document.getElementById('otpModal').classList.remove('show');
}

// ========== OTP VARIABLES ==========
let otpTimer = null;
let otpTimeLeft = 60;

let currentApplicationData = null;


// ========== OTP FUNCTIONS ==========

function initOTPFunctionality() {
  // Show modal on load (with session check)
  // setTimeout(checkExistingSession, 500);

  document.getElementById('phoneInput').addEventListener('input', function (e) {
    this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);
    clearOTPErrors();
  });

  document.querySelectorAll('.otp-digit').forEach((digit, idx) => {
    digit.addEventListener('input', function () {
      this.value = this.value.replace(/[^0-9]/g, '');
      if (this.value && idx < 5) {
        document.querySelectorAll('.otp-digit')[idx + 1].focus();
      }
      clearOTPErrors();
    });
  });

  document.getElementById('sendOtpBtn').addEventListener('click', function () {
    const phone = document.getElementById('phoneInput').value;
    if (!validatePhoneNumber(phone)) {
      document.getElementById('phoneError').style.display = 'block';
      return;
    }
    this.disabled = true;
    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
    sendOTPRequest(phone);
  });

  document.getElementById('verifyOtpBtn').addEventListener('click', function () {
    const phone = document.getElementById('phoneInput').value;
    const otp = collectOTP();
    if (otp.length !== 6) {
      document.getElementById('otpError').textContent = 'Enter 6-digit OTP';
      document.getElementById('otpError').style.display = 'block';
      return;
    }
    this.disabled = true;
    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying...';
    verifyOTPRequest(phone, otp);
  });

  document.getElementById('resendOtpBtn').addEventListener('click', function () {
    const phone = document.getElementById('phoneInput').value;
    if (!validatePhoneNumber(phone)) {
      document.getElementById('phoneError').style.display = 'block';
      return;
    }
    this.disabled = true;
    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Resending...';
    sendOTPRequest(phone);
  });
}
function startOtpTimer() {
  otpTimeLeft = 60;
  const timerElement = document.getElementById('timerCount');
  const resendBtn = document.getElementById('resendOtpBtn');

  resendBtn.style.display = 'none';
  document.getElementById('verifyOtpBtn').style.display = 'inline-flex';

  clearInterval(otpTimer);
  otpTimer = setInterval(() => {
    otpTimeLeft--;
    timerElement.textContent = otpTimeLeft;

    if (otpTimeLeft <= 0) {
      clearInterval(otpTimer);
      resendBtn.style.display = 'inline-flex';
      document.getElementById('verifyOtpBtn').style.display = 'none';
    }
  }, 1000);
}

function validatePhoneNumber(phone) {
  return /^[6-9][0-9]{9}$/.test(phone);
}

function collectOTP() {
  const digits = document.querySelectorAll('.otp-digit');
  let otp = '';
  digits.forEach(digit => otp += digit.value);
  return otp.trim();
}

function clearOTPErrors() {
  document.getElementById('phoneError').style.display = 'none';
  document.getElementById('otpError').style.display = 'none';
  document.getElementById('otpSuccess').style.display = 'none';
}

// Send OTP API call
function sendOTPRequest(phoneNumber) {
  const payload = {
    action: "otp_operation",
    operation: "send_otp",
    mobile_no: phoneNumber
  };

  $.ajax({
    url: `${api_base_url}/v1/fn_otp_operation`,
    type: "POST",
    headers: { "X-App-Key": "TN_EKEE", "X-App-Name": "TN_EKEE" },
    data: { data: encryptData(payload) },
    dataType: "json",
    success: function (response) {
      if (response.success === 1) {
        document.getElementById('otpSection').style.display = 'block';
        startOtpTimer();
        document.getElementById('otpSuccess').textContent = 'OTP sent successfully!';
        document.getElementById('otpSuccess').style.display = 'block';
      } else {
        document.getElementById('otpError').textContent = response.message || 'Failed to send OTP';
        document.getElementById('otpError').style.display = 'block';
      }
    },
    error: function () {
      document.getElementById('otpError').textContent = 'Network error. Try again.';
      document.getElementById('otpError').style.display = 'block';
    }
  });
}

// Verify OTP API call
function verifyOTPRequest(phoneNumber, otp) {
  const payload = {
    action: "otp_operation",
    operation: "verify_otp",
    mobile_no: phoneNumber,
    otp: otp
  };

  $.ajax({
    url: `${api_base_url}/v1/fn_otp_operation`,
    type: "POST",
    headers: { "X-App-Key": "TN_EKEE", "X-App-Name": "TN_EKEE" },
    data: { data: encryptData(payload) },
    dataType: "json",
    success: function (response) {
      if (response.success === 1 && response.verified === true) {
        // Store session
        localStorage.setItem('otp_verified', 'true');
        localStorage.setItem('verified_mobile', phoneNumber);
        localStorage.setItem('otp_verified_timestamp', new Date().getTime().toString());
        localStorage.setItem('user_data', JSON.stringify(response.user));
        localStorage.setItem('response', JSON.stringify(response));
        localStorage.setItem('user_data_timestamp', new Date().getTime().toString());
        localStorage.setItem('user_count', response.user_count);
        localStorage.setItem('application_id', response.user ? response.user.application_id : '');
        localStorage.setItem('user_mapping', JSON.stringify(response.user_mapping));
        $('#phone').val(phoneNumber);


        // Update UI
        loggedInPhone = phoneNumber;
        document.getElementById('userBadge').style.display = 'flex';
        document.getElementById('loggedInPhone').textContent = phoneNumber;

        hideOTPModal();

        // Decide new or existing user
        if (response.user_count > 0 && response.user) {
          handleExistingApplication(response);
        } else {
          handleNewUser(phoneNumber);
        }
      } else {
        document.getElementById('otpError').textContent = response.message || 'Invalid OTP';
        document.getElementById('otpError').style.display = 'block';
      }
    },
    error: function () {
      document.getElementById('otpError').textContent = 'Verification failed. Try again.';
      document.getElementById('otpError').style.display = 'block';
    },
    complete: function () {
      const verifyBtn = document.getElementById('verifyOtpBtn');
      verifyBtn.innerHTML = '<i class="fas fa-check-circle"></i> Verify & Continue';
      verifyBtn.disabled = false;
    }
  });
}
function check_existing_application(phoneNumber) {
  const payload = {
    action: "otp_operation",
    operation: "get_user_details",
    mobile_no: phoneNumber

  };

  $.ajax({
    url: `${api_base_url}/v1/fn_otp_operation`,
    type: "POST",
    headers: { "X-App-Key": "TN_EKEE", "X-App-Name": "TN_EKEE" },
    data: { data: encryptData(payload) },
    dataType: "json",
    success: function (response) {
      if (response.success === 1 && response.verified === true) {
        // Store session
        localStorage.setItem('otp_verified', 'true');
        localStorage.setItem('verified_mobile', phoneNumber);
        localStorage.setItem('otp_verified_timestamp', new Date().getTime().toString());
        localStorage.setItem('user_data', JSON.stringify(response.user));
        localStorage.setItem('response', JSON.stringify(response));
        localStorage.setItem('user_data_timestamp', new Date().getTime().toString());
        localStorage.setItem('user_count', response.user_count);
        localStorage.setItem('application_id', response.user ? response.user.application_id : '');
        localStorage.setItem('user_mapping', JSON.stringify(response.user_mapping));


        // Update UI
        loggedInPhone = phoneNumber;
        document.getElementById('userBadge').style.display = 'flex';
        document.getElementById('loggedInPhone').textContent = phoneNumber;

        hideOTPModal();

        // Decide new or existing user
        if (response.user_count > 0 && response.user) {
          handleExistingApplication(response);
        } else {
          handleNewUser(phoneNumber);
        }
      } else {
        document.getElementById('otpError').textContent = response.message || 'Invalid OTP';
        document.getElementById('otpError').style.display = 'block';
      }
    },
    error: function () {
      document.getElementById('otpError').textContent = 'Verification failed. Try again.';
      document.getElementById('otpError').style.display = 'block';
    },
    complete: function () {
      const verifyBtn = document.getElementById('verifyOtpBtn');
      verifyBtn.innerHTML = '<i class="fas fa-check-circle"></i> Verify & Continue';
      verifyBtn.disabled = false;
    }
  });
}



document.addEventListener('change', function (e) {
  if (e.target.matches('input[type="radio"]')) {

    // Only act if this is meetIcon
    if (!e.target.name.includes('meetIcon')) return;

    // Get the parent .dream-line2 of the clicked radio
    const line2 = e.target.closest('.dream-row');
    if (!line2) return;

    // Check if the input already exists
    let inputBox = line2.querySelector('.extra-input');
    if (!inputBox) {
      // Create input box dynamically
      inputBox = document.createElement('div');
      inputBox.className = 'extra-input';
      inputBox.style.marginTop = '8px';
      inputBox.innerHTML = `
        <input type="text" class="small-input" style="width:400px" placeholder="Name of the icon you want to meet">
      `;
      line2.appendChild(inputBox);
    }

    // Show the input
    inputBox.style.display = 'block';
  }
});


const immediateDreamConfig = [
  {
    key: 'meetIcon',
    label: 'A. Meet an Icon who inspires you',
    supports: [
      { type: 'option', text: 'Sports' },
      { type: 'option', text: 'Arts & Culture' },
      { type: 'option', text: 'Science & Technology' },
      { type: 'option', text: 'Education & Academia' },
      { type: 'option', text: 'Social Service & Activism' },
      { type: 'option', text: 'Business & Entrepreneurship' },
      { type: 'option', text: 'Literature & Writing' },
      { type: 'option', text: 'Healthcare & Medicine' },
      { type: 'option', text: 'Agriculture & Rural Development' },
      { type: 'option', text: 'Music & Performing Arts' },
      { type: 'option', text: 'Law & Judiciary' },
      { type: 'option', text: 'Journalism & Media' }
    ],
    extraInputLabel: 'Name of the icon you want to meet'
  },
  {
    key: 'techTools',
    label: 'B. Technology & tools to connect online',
    supports: [
      { type: 'option', text: 'Laptop for College students' },
      { type: 'option', text: 'Data pack' },
      { type: 'option', text: 'Free AI subscription' },
      { type: 'option', text: 'Online certification courses support' }
    ]
  },
  {
    key: 'trainingJobs',
    label: 'C. Get training for real jobs',
    supports: [
      { type: 'option', text: 'Career Counselling' },
      { type: 'option', text: 'Vocational Training' },
      { type: 'option', text: 'Factory Apprenticeship' },
      { type: 'option', text: 'Internship Opportunities' },
      { type: 'option', text: 'Entrepreneurship Bootcamp' }
    ]
  },
  {
    key: 'sports',
    label: 'D. Get fit, compete and play sports',
    supports: [
      { type: 'heading', text: 'a) Access to Sports Kits' },
      { type: 'option', text: 'Sports Kit - Cricket' },
      { type: 'option', text: 'Sports Kit - Football' },
      { type: 'option', text: 'Sports Kit - Volleyball' },
      { type: 'heading', text: 'b) Access to Sports Facilities' },
      { type: 'option', text: 'Access to training grounds' },
    ]
  },
  {
    key: 'community',
    label: 'E. Community Engagement',
    supports: [
      { type: 'option', text: 'District Youth Corps volunteering • Organize health camps • Conduct disaster preparedness programs • Anti-drug campaigns • Mental health outreach • Environment protection activities (Volunteers receive certificate & coupons)' },
      { type: 'option', text: 'Youth Government Ambassador (Dedicated ambassadors appointed by departments to support initiatives)' }
    ]
  }
];

const fiveYearConfig = [
  {
    key: 'competitiveExams',
    label: 'A. Support for Competitive Exams',
    supports: [
      { type: 'option', text: 'UPSC / Group A-B Central Services Coaching' },
      { type: 'option', text: 'State Exams (TNPSC / SI / TET / TRB / etc.)' },
      { type: 'option', text: 'BSRB (Banking Services Recruitment Board)' }
    ]
  },
  {
    key: 'higherEducation',
    label: 'B. Pursue Higher Education',
    supports: [
      { type: 'option', text: 'Study Material Provisions' },
      { type: 'option', text: 'Academic Mentorship for Higher Studies (M.Phil. / PhD / Labs)' },
      { type: 'option', text: 'Government Scholarship Support (Fee Waiver / Merit Scholarships)' },
      { type: 'option', text: 'Hostel / Accommodation Support for Students Studying Away from Home' },
      { type: 'option', text: 'Bridge Courses & Foundation Programs for Rural / First-Generation Learners' }
    ]
  },
  {
    key: 'globalLearning',
    label: 'C. Global Learning Exposure',
    supports: [
      { type: 'option', text: 'Advise & Counselling to study abroad' },
      { type: 'option', text: 'Language Training for global education' }
    ]
  },
  {
    key: 'meaningfulEmployment',
    label: 'D. Meaningful Employment',
    supports: [
      { type: 'heading', text: 'a) Job Access Pathways' },
      { type: 'option', text: 'Access to Job Melas' },
      { type: 'option', text: 'Access to government-sponsored co-working spaces' },
      { type: 'option', text: 'Enable abroad job opportunities (through coordination with NRTs)' }
    ]
  },
  {
    key: 'selfEmployment',
    label: 'E. Self-Employment & Business Growth',
    supports: [
      { type: 'heading', text: 'a) Starting a Business' },
      { type: 'option', text: 'Startup seed grant' },
      { type: 'option', text: 'Business mentorship' },
      { type: 'option', text: 'Incubation center support' },
      { type: 'heading', text: 'b) If you are already a business owner' },
      { type: 'option', text: 'Market linkage & branding support' },
      { type: 'option', text: 'Women-led business funding' },
      { type: 'option', text: 'Support for SHGs' }
    ]
  },
  {
    key: 'mentalHealth',
    label: 'F. Mental & Emotional Health',
    supports: [
      { type: 'option', text: 'Mental health counselling' },
      { type: 'option', text: 'Nutrition & lifestyle guidance' },
      { type: 'option', text: 'Stress management workshops' }
    ]
  }
];
function renderDreams(containerId, config, prefix) {
  const wrap = document.getElementById(containerId);
  wrap.innerHTML = '';

  config.forEach((d) => {
    const id = prefix + '_' + d.key;
    const row = document.createElement('div');
    row.className = 'dream-row';

    // -------------------------
    // LINE 1: Dream + Priority
    // -------------------------
    const line1 = document.createElement('div');
    line1.className = "dream-line1";

    // Checkbox + Label
    const dreamBox = document.createElement('label');
    dreamBox.style.fontWeight = 600;
    dreamBox.innerHTML = `
      <input type="checkbox" data-key="${d.key}" class="dream-checkbox" id="chk_${id}" />
      <span style="margin-left:8px">${d.label}</span>
    `;

    // Priority Dropdown
    const priority = document.createElement('div');
    priority.innerHTML = `
      <select class="priority small-input" data-section="${containerId}" id="prio_${id}" disabled>
        <option value="">Priority</option>
        <option value="1">1</option>
        <option value="2">2</option>
      </select>
    `;

    line1.appendChild(dreamBox);
    line1.appendChild(priority);

    // -------------------------
    // LINE 2: Radio buttons
    // -------------------------
    const line2 = document.createElement('div');
    line2.className = "dream-line2 support-radio-group";

    d.supports.forEach((s, index) => {
      if (s.type === 'heading') {
        const h = document.createElement('div');
        h.className = 'support-heading';
        h.textContent = s.text;
        line2.appendChild(h);
      } else if (s.type === 'option') {
        const label = document.createElement('label');
        label.className = 'support-radio-label';
        label.innerHTML = `<input type="radio" name="sup_${id}" value="${s.text}" disabled> ${s.text}`;
        line2.appendChild(label);
      }

    });

    // Add both lines into main row
    row.appendChild(line1);
    row.appendChild(line2);

    wrap.appendChild(row);
  });
}

// render both sets
renderDreams('immediateDreams', immediateDreamConfig, 'imm');
renderDreams('fiveYearDreams', fiveYearConfig, 'fiv');

// ---------- Priority Control Logic ----------
const usedPriorities = {
  immediateDreams: new Set(),
  fiveYearDreams: new Set()
};

function updateUsedPriorities(sectionId) {
  usedPriorities[sectionId].clear();
  document.querySelectorAll(`#${sectionId} .priority`).forEach(sel => {
    if (sel.value) usedPriorities[sectionId].add(sel.value);
  });
}

function updatePriorityOptions(sectionId) {
  document.querySelectorAll(`#${sectionId} .priority`).forEach(sel => {
    const currentVal = sel.value;

    Array.from(sel.options).forEach(opt => {
      if (!opt.value) return; // skip "Priority" placeholder

      // Used in this section and not the current selected value
      if (usedPriorities[sectionId].has(opt.value) && opt.value !== currentVal) {
        opt.disabled = true;
        opt.style.display = 'none';      // HIDE the option
      } else {
        opt.disabled = false;
        opt.style.display = 'block';     // SHOW the option
      }
    });
  });
}

// ---------- Main Interaction Logic ----------
document.addEventListener('change', (e) => {
  // Checkbox toggle (limit only)
  if (e.target.classList.contains('dream-checkbox')) {
    const sectionId = e.target.closest('#immediateDreams') ? 'immediateDreams' : 'fiveYearDreams';
    enforceMaxSelections(sectionId);
  }

  // Priority dropdown change
  if (e.target.classList.contains('priority')) {
    const sectionId = e.target.dataset.section;
    updateUsedPriorities(sectionId);
    updatePriorityOptions(sectionId);
  }

  // Auto-check when dropdown selected
  if (e.target.classList.contains('priority') || e.target.classList.contains('support')) {
    const id = e.target.id.replace(/^(prio|sup)_/, '');
    const chk = document.getElementById('chk_' + id);
    if (e.target.value !== '') chk.checked = true;
    const sectionId = e.target.closest('#immediateDreams') ? 'immediateDreams' : 'fiveYearDreams';
    enforceMaxSelections(sectionId);
  }

  // Employment toggle
  if (e.target.id === 'employmentStatus') {
    const wrap = document.getElementById('employmentTypeWrap');
    wrap.style.display = (e.target.value === 'Employed') ? 'block' : 'none';
  }

  // Age autofill
  if (e.target.id === 'dob') {
    updateAgeFromDOB();
  }
});

// ---------- Helper Functions ----------
function enforceMaxSelections(sectionId) {
  const container = document.getElementById(sectionId);
  const rows = container.querySelectorAll('.dream-row');
  const checkedCount = Array.from(container.querySelectorAll('.dream-checkbox')).filter(c => c.checked).length;
  const maxReached = checkedCount >= 2;

  rows.forEach(row => {
    const chk = row.querySelector('.dream-checkbox');
    const pri = row.querySelector('.priority');
    const supGroup = row.querySelector('.support-radio-group'); // updated

    if (chk.checked) {

      // Selected rows remain fully enabled
      chk.disabled = false;
      pri.disabled = false;

      // ENABLE all radio buttons
      supGroup.querySelectorAll('input[type="radio"]').forEach(r => r.disabled = false);

      row.classList.remove('disabled');

    } else {

      if (maxReached) {

        // When limit reached, unselected rows disabled + reset
        chk.disabled = true;

        // Reset priority
        pri.value = '';
        pri.disabled = true;

        // Reset and disable radio buttons
        supGroup.querySelectorAll('input[type="radio"]').forEach(r => {
          r.checked = false;
          r.disabled = true;
        });

        row.classList.add('disabled');

      } else {

        // Not selected and limit not reached
        chk.disabled = false;

        // Reset & disable priority
        pri.value = '';
        pri.disabled = true;

        // Reset & disable radios
        supGroup.querySelectorAll('input[type="radio"]').forEach(r => {
          r.checked = false;
          r.disabled = true;
        });

        row.classList.remove('disabled');
      }
    }
  });
}

// ---------- Initialize state after render ----------
enforceMaxSelections('immediateDreams');
enforceMaxSelections('fiveYearDreams');
updateUsedPriorities('immediateDreams');
updateUsedPriorities('fiveYearDreams');
updatePriorityOptions('immediateDreams');
updatePriorityOptions('fiveYearDreams');

function updateAgeFromDOB() {
  const dob = document.getElementById('dob').value;
  const ageField = document.getElementById('age');
  if (!dob) { ageField.value = ''; return }
  const birth = new Date(dob);
  const today = new Date();
  let age = today.getFullYear() - birth.getFullYear();
  const m = today.getMonth() - birth.getMonth();
  if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;
  ageField.value = age;
}

function collectDreamResponses(containerId, prefix) {
  const container = document.getElementById(containerId);
  const rows = container.querySelectorAll('.dream-row');
  const responses = [];
  
  rows.forEach(row => {
    const chk = row.querySelector('.dream-checkbox');
    if (chk && chk.checked) {
      const id = chk.id.replace('chk_', '');
      const priority = row.querySelector('.priority').value;
      
      // Get selected radio button value
      const radioGroup = row.querySelector('.support-radio-group');
      let selectedSupport = null;
      if (radioGroup) {
        const selectedRadio = radioGroup.querySelector('input[type="radio"]:checked');
        if (selectedRadio) {
          selectedSupport = selectedRadio.value;
        }
      }
      
      // Get the dream label (remove checkbox span if present)
      let label = chk.closest('label').textContent.trim();
      // Remove any checkbox indicator text if present
      label = label.replace(/^\s*/, '');
      
      responses.push({
        key: chk.dataset.key,
        priority: priority || null,
        support: selectedSupport,
        label: label
      });
    }
  });
  return responses;
}
function validatePriorities(arr) {
  const prios = arr.map(a => a.priority).filter(Boolean);
  return prios.length === new Set(prios).size;
}

// ---------- Submit / Draft ----------
function handleSubmit(e) {
  e.preventDefault();
  const form = document.getElementById('dreamForm');
  const age = Number(document.getElementById('age').value);
  
  if (isNaN(age) || age < 18 || age > 29) {
    showMessage('Applicants must be between 18 and 29 years old.', 'error');
    return false;
  }

  // Corrected: Add the prefix parameter
  const immediate = collectDreamResponses('immediateDreams', 'imm');
  const fiveYr = collectDreamResponses('fiveYearDreams', 'fiv');
  
  console.log('Immediate dreams:', immediate); // Debug log
  console.log('Five year dreams:', fiveYr); // Debug log
  
  if (immediate.length === 0 && fiveYr.length === 0) {
    showMessage('Please choose at least one dream in immediate or next-5-years sections.', 'error');
    return false;
  }
  
  if (!validatePriorities(immediate) || !validatePriorities(fiveYr)) {
    showMessage('Duplicate priorities found in a section. Please ensure priorities are unique (1-3) within each section.', 'error');
    return false;
  }

  const payload = {
    name: form.name.value,
    gender: form.gender.value,
    email: form.email.value,
    district: form.district.value,
    age: age,
    dob: form.dob.value,
    education: form.education.value,
    employmentStatus: form.employmentStatus.value,
    employmentType: form.employmentType.value || null,
    immediateDreams: immediate,
    fiveYearDreams: fiveYr,
    otherAspirations: form.otherAspirations.value || null,
    timestamps: { submittedAt: new Date().toISOString() }
  };

  console.log('Final payload:', payload); // Debug log to check if data is captured
  return false;
}



// save as json
function saveDraft() {
  const form = document.getElementById('dreamForm');
  const payload = {
    name: form.name.value,
    email: form.email.value,
    district: form.district.value,
    age: document.getElementById('age').value,
    dob: form.dob.value,
    immediateDreams: collectDreamResponses('immediateDreams'),
    fiveYearDreams: collectDreamResponses('fiveYearDreams'),
    otherAspirations: form.otherAspirations.value
  };
  const blob = new Blob([JSON.stringify(payload, null, 2)], { type: 'application/json' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'enn_kanavu_draft.json';
  document.body.appendChild(a);
  a.click();
  a.remove();
  showMessage('Draft downloaded locally.', 'success');
}

// save as pdf
function saveDraftPDF() {
  const form = document.getElementById('dreamForm');

  const payload = {
    name: form.name.value,
    email: form.email.value,
    district: form.district.value,
    age: document.getElementById('age').value,
    dob: form.dob.value,
    immediateDreams: collectDreamResponses('immediateDreams'),
    fiveYearDreams: collectDreamResponses('fiveYearDreams'),
    otherAspirations: form.otherAspirations.value
  };

  const { jsPDF } = window.jspdf;
  const doc = new jsPDF();

  let y = 10;
  doc.setFontSize(14);
  doc.text("Enn Kanavu - Draft PDF", 10, y);
  y += 10;

  doc.setFontSize(11);

  function addLine(text) {
    doc.text(text, 10, y);
    y += 7;
    if (y > 280) { doc.addPage(); y = 10; }
  }

  addLine(`Name: ${payload.name || ''}`);
  addLine(`Email: ${payload.email || ''}`);
  addLine(`District: ${payload.district || ''}`);
  addLine(`Age: ${payload.age || ''}`);
  addLine(`DOB: ${payload.dob || ''}`);
  addLine("");

  addLine("Immediate Dreams:");
  payload.immediateDreams.forEach(d => {
    addLine(`- ${d.label} (Priority: ${d.priority}, Support: ${d.support})`);
  });
  addLine("");

  addLine("Five-Year Dreams:");
  payload.fiveYearDreams.forEach(d => {
    addLine(`- ${d.label} (Priority: ${d.priority}, Support: ${d.support})`);
  });
  addLine("");

  addLine(`Other Aspirations: ${payload.otherAspirations || ''}`);

  doc.save("enn_kanavu_draft.pdf");

  showMessage('Draft PDF downloaded.', 'success');
}


function showMessage(msg, type = 'info') {
  const el = document.getElementById('formMessage');
  el.innerText = msg;
  el.style.color = (type === 'error') ? '#b91c1c' : '#064e3b';
  setTimeout(() => { el.innerText = ''; }, 6000);
}



// popup
function showSuccessPopup() {
  const popup = document.getElementById("successPopup");
  if (!popup) return;

  popup.classList.add("show");

  const closeBtn = document.getElementById("popupCloseBtn");
  if (closeBtn) {
    closeBtn.onclick = () => popup.classList.remove("show");
  }

  // Close when clicking outside the popup box
  popup.addEventListener("click", (e) => {
    if (e.target.id === "successPopup") {
      popup.classList.remove("show");
    }
  });
}


/////////////////////////////

async function checkExistingSession() {
  const isVerified = localStorage.getItem('otp_verified') === 'true';
  const verifiedMobile = localStorage.getItem('verified_mobile');
  const timestamp = localStorage.getItem('otp_verified_timestamp');
  const now = Date.now();

  const isSessionValid = isVerified && verifiedMobile && timestamp &&
    (now - parseInt(timestamp)) < (24 * 60 * 60 * 1000); // 24 hours

  if (!isSessionValid) {
    // Fresh visitor or expired session
    localStorage.clear(); // optional: clean up old data
    showOTPModal();
    return;
  }

  // Valid session exists
  loggedInPhone = verifiedMobile;

  // Update header badge
  document.getElementById('userBadge').style.display = 'flex';
  document.getElementById('loggedInPhone').textContent = verifiedMobile;
  document.getElementById('logoutBtn').style.display = 'inline-block';
   $('#phone').val(verifiedMobile).attr('readonly', true);

  // Now check with backend: does this phone have a submitted application?
  await checkApplicationStatus(verifiedMobile);
}

async function checkApplicationStatus(phone) {
  const payload = {
    action: "otp_operation",
    operation: "get_user_details",
    mobile_no: phone
  };

  $.ajax({
    url: `${api_base_url}/v1/fn_otp_operation`,
    type: "POST",
    headers: { "X-App-Key": "TN_EKEE", "X-App-Name": "TN_EKEE" },
    data: { data: encryptData(payload) },
    dataType: "json",
    success: function (response) {
      if (response.success === 1 && response.user_count > 0 && response.user) {
        // User has already submitted an application
        // handleExistingApplication(response);
      } else {
        // New user or draft-only user
        // handleNewUser(phone);
      }

      // In both cases, hide OTP modal and enable form as needed
      hideOTPModal();
    },
    error: function () {
      console.error("Failed to check application status");
      // Fallback: treat as new user
      handleNewUser(phone);
      hideOTPModal();
    }
  });
}

function setupLogoutButton() {
  const logoutBtn = document.getElementById('logoutBtn');
  logoutBtn.addEventListener('click', function () {
    logoutUser();
  });
}
function logoutUser() {
  Swal.fire({
    title: 'Logout?',
    text: 'Are you sure you want to logout?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#ef4444',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Yes, Logout',
    cancelButtonText: 'Cancel'
  }).then((result) => {
    if (result.isConfirmed) {
      // Clear all localStorage data
      localStorage.removeItem('otp_verified');
      localStorage.removeItem('verified_mobile');
      localStorage.removeItem('otp_verified_timestamp');
      localStorage.removeItem('application_id');

      // Hide user info and logout button
      //   document.getElementById('userInfo').style.display = 'none';
      document.getElementById('logoutBtn').style.display = 'none';

      // Reset form
      location.reload();

    }
  });
}