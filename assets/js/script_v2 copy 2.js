//do not touch
// total source and validation for ekee form



$(document).ready(function () {
  checkExistingSession();


  initOTPFunctionality();
  fetchImmediateDreams();
  fetchFiveYearDreams();
  getDistrictDropDown();

})


function getDistrictDropDown() {
  const payload = {
    action: "select",
    _table_name: "district",
    selected_columns: ["district_name", "district_id"],
    filter_conditions: {},
    sort_columns: { "district_name": "asc" }
  };

  $.ajax({
    url: `${api_base_url}/v1/dynamic_input`,
    type: "POST",
    headers: {
      "X-App-Key": "TN_EKEE",
      "X-App-Name": "TN_EKEE"
    },
    data: {
      data: encryptData(payload)
    },
    dataType: "json",
    success: function (response) {
      if (response.success === 1) {
        const districtSelect = document.getElementById('district');
        response.data.forEach(district => {
          const option = document.createElement('option');
          option.value = district.district_id;
          option.textContent = district.district_name;
          districtSelect.appendChild(option);
        });
      }
    },
    error: function () {
      console.error('Error loading districts');
    }
  });
}

function sanitizeInput(str) {
  if (typeof str !== 'string') return str;
  return str.trim()
    .replace(/[<>"']/g, char => ({
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#39;'
    }[char]));
}
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


// const immediateDreamConfig = [
//   {
//     key: 'meetIcon',
//     label: 'A. Meet an Icon who inspires you',
//     supports: [
//       { type: 'option', text: 'Sports' },
//       { type: 'option', text: 'Arts & Culture' },
//       { type: 'option', text: 'Science & Technology' },
//       { type: 'option', text: 'Education & Academia' },
//       { type: 'option', text: 'Social Service & Activism' },
//       { type: 'option', text: 'Business & Entrepreneurship' },
//       { type: 'option', text: 'Literature & Writing' },
//       { type: 'option', text: 'Healthcare & Medicine' },
//       { type: 'option', text: 'Agriculture & Rural Development' },
//       { type: 'option', text: 'Music & Performing Arts' },
//       { type: 'option', text: 'Law & Judiciary' },
//       { type: 'option', text: 'Journalism & Media' }
//     ],
//     extraInputLabel: 'Name of the icon you want to meet'
//   },
//   {
//     key: 'techTools',
//     label: 'B. Technology & tools to connect online',
//     supports: [
//       { type: 'option', text: 'Laptop for College students' },
//       { type: 'option', text: 'Data pack' },
//       { type: 'option', text: 'Free AI subscription' },
//       { type: 'option', text: 'Online certification courses support' }
//     ]
//   },
//   {
//     key: 'trainingJobs',
//     label: 'C. Get training for real jobs',
//     supports: [
//       { type: 'option', text: 'Career Counselling' },
//       { type: 'option', text: 'Vocational Training' },
//       { type: 'option', text: 'Factory Apprenticeship' },
//       { type: 'option', text: 'Internship Opportunities' },
//       { type: 'option', text: 'Entrepreneurship Bootcamp' }
//     ]
//   },
//   {
//     key: 'sports',
//     label: 'D. Get fit, compete and play sports',
//     supports: [
//       { type: 'heading', text: 'a) Access to Sports Kits' },
//       { type: 'option', text: 'Sports Kit - Cricket' },
//       { type: 'option', text: 'Sports Kit - Football' },
//       { type: 'option', text: 'Sports Kit - Volleyball' },
//       { type: 'heading', text: 'b) Access to Sports Facilities' },
//       { type: 'option', text: 'Access to training grounds' },
//     ]
//   },
//   {
//     key: 'community',
//     label: 'E. Community Engagement',
//     supports: [
//       { type: 'option', text: 'District Youth Corps volunteering • Organize health camps • Conduct disaster preparedness programs • Anti-drug campaigns • Mental health outreach • Environment protection activities (Volunteers receive certificate & coupons)' },
//       { type: 'option', text: 'Youth Government Ambassador (Dedicated ambassadors appointed by departments to support initiatives)' }
//     ]
//   }
// ];

// const fiveYearConfig = [
//   {
//     key: 'competitiveExams',
//     label: 'A. Support for Competitive Exams',
//     supports: [
//       { type: 'option', text: 'UPSC / Group A-B Central Services Coaching' },
//       { type: 'option', text: 'State Exams (TNPSC / SI / TET / TRB / etc.)' },
//       { type: 'option', text: 'BSRB (Banking Services Recruitment Board)' }
//     ]
//   },
//   {
//     key: 'higherEducation',
//     label: 'B. Pursue Higher Education',
//     supports: [
//       { type: 'option', text: 'Study Material Provisions' },
//       { type: 'option', text: 'Academic Mentorship for Higher Studies (M.Phil. / PhD / Labs)' },
//       { type: 'option', text: 'Government Scholarship Support (Fee Waiver / Merit Scholarships)' },
//       { type: 'option', text: 'Hostel / Accommodation Support for Students Studying Away from Home' },
//       { type: 'option', text: 'Bridge Courses & Foundation Programs for Rural / First-Generation Learners' }
//     ]
//   },
//   {
//     key: 'globalLearning',
//     label: 'C. Global Learning Exposure',
//     supports: [
//       { type: 'option', text: 'Advise & Counselling to study abroad' },
//       { type: 'option', text: 'Language Training for global education' }
//     ]
//   },
//   {
//     key: 'meaningfulEmployment',
//     label: 'D. Meaningful Employment',
//     supports: [
//       { type: 'heading', text: 'a) Job Access Pathways' },
//       { type: 'option', text: 'Access to Job Melas' },
//       { type: 'option', text: 'Access to government-sponsored co-working spaces' },
//       { type: 'option', text: 'Enable abroad job opportunities (through coordination with NRTs)' }
//     ]
//   },
//   {
//     key: 'selfEmployment',
//     label: 'E. Self-Employment & Business Growth',
//     supports: [
//       { type: 'heading', text: 'a) Starting a Business' },
//       { type: 'option', text: 'Startup seed grant' },
//       { type: 'option', text: 'Business mentorship' },
//       { type: 'option', text: 'Incubation center support' },
//       { type: 'heading', text: 'b) If you are already a business owner' },
//       { type: 'option', text: 'Market linkage & branding support' },
//       { type: 'option', text: 'Women-led business funding' },
//       { type: 'option', text: 'Support for SHGs' }
//     ]
//   },
//   {
//     key: 'mentalHealth',
//     label: 'F. Mental & Emotional Health',
//     supports: [
//       { type: 'option', text: 'Mental health counselling' },
//       { type: 'option', text: 'Nutrition & lifestyle guidance' },
//       { type: 'option', text: 'Stress management workshops' }
//     ]
//   }
// ];
// function renderDreams(containerId, config, prefix) {
//   const wrap = document.getElementById(containerId);
//   wrap.innerHTML = '';

//   config.forEach((d) => {
//     const id = prefix + '_' + d.key;
//     const row = document.createElement('div');
//     row.className = 'dream-row';

//     // -------------------------
//     // LINE 1: Dream + Priority
//     // -------------------------
//     const line1 = document.createElement('div');
//     line1.className = "dream-line1";

//     // Checkbox + Label
//     const dreamBox = document.createElement('label');
//     dreamBox.style.fontWeight = 600;
//     dreamBox.innerHTML = `
//       <input type="checkbox" data-key="${d.key}" class="dream-checkbox" id="chk_${id}" />
//       <span style="margin-left:8px">${d.label}</span>
//     `;

//     // Priority Dropdown
//     const priority = document.createElement('div');
//     priority.innerHTML = `
//       <select class="priority small-input" data-section="${containerId}" id="prio_${id}" disabled>
//         <option value="">Priority</option>
//         <option value="1">1</option>
//         <option value="2">2</option>
//       </select>
//     `;

//     line1.appendChild(dreamBox);
//     line1.appendChild(priority);

//     // -------------------------
//     // LINE 2: Radio buttons
//     // -------------------------
//     const line2 = document.createElement('div');
//     line2.className = "dream-line2 support-radio-group";

//     d.supports.forEach((s, index) => {
//       if (s.type === 'heading') {
//         const h = document.createElement('div');
//         h.className = 'support-heading';
//         h.textContent = s.text;
//         line2.appendChild(h);
//       } else if (s.type === 'option') {
//         const label = document.createElement('label');
//         label.className = 'support-radio-label';
//         label.innerHTML = `<input type="radio" name="sup_${id}" value="${s.text}" disabled> ${s.text}`;
//         line2.appendChild(label);
//       }

//     });

//     // Add both lines into main row
//     row.appendChild(line1);
//     row.appendChild(line2);

//     wrap.appendChild(row);
//   });
// }


function fetchImmediateDreams() {
  const payload = {
    action: "function_call",
    function_name: "get_support_config_group",
    params: { group: 'immediate' }
  };

  $.ajax({
    url: `${api_base_url}/v1/commonfunction`,
    type: "POST",
    headers: {
      "X-App-Key": "TN_EKEE",
      "X-App-Name": "TN_EKEE"
    },
    data: {
      data: encryptData(payload)
    },
    dataType: "json",
    success: function (response) {
      try {
        const decrypted = decryptData(response.data);
        const parsed = JSON.parse(decrypted[0]['get_support_config_group']);
        immediateDreamConfig = parsed;
        renderDreams('immediateDreams', immediateDreamConfig, 'imm');
      } catch (e) {
        console.error("Error parsing immediate dreams:", e);
        document.getElementById('immediateLoading').innerHTML =
          '<div class="loading-text">Error loading dreams. Please refresh.</div>';
      }
    },
    error: function (xhr, status, error) {
      console.error("Error fetching immediate dreams:", error);
      document.getElementById('immediateLoading').innerHTML =
        '<div class="loading-text">Error loading dreams. Please refresh.</div>';
    }
  });
}

function fetchFiveYearDreams() {
  const payload = {
    action: "function_call",
    function_name: "get_support_config_group",
    params: { group: 'five_year' }
  };

  $.ajax({
    url: `${api_base_url}/v1/commonfunction`,
    type: "POST",
    headers: {
      "X-App-Key": "TN_EKEE",
      "X-App-Name": "TN_EKEE"
    },
    data: {
      data: encryptData(payload)
    },
    dataType: "json",
    success: function (response) {
      try {
        const decrypted = decryptData(response.data);
        const parsed = JSON.parse(decrypted[0]['get_support_config_group']);
        fiveYearConfig = parsed;
        renderDreams('fiveYearDreams', fiveYearConfig, 'fiv');
      } catch (e) {
        console.error("Error parsing five-year dreams:", e);
        document.getElementById('fiveYearLoading').innerHTML =
          '<div class="loading-text">Error loading dreams. Please refresh.</div>';
      }
    },
    error: function (xhr, status, error) {
      console.error("Error fetching five-year dreams:", error);
      document.getElementById('fiveYearLoading').innerHTML =
        '<div class="loading-text">Error loading dreams. Please refresh.</div>';
    }
  });
}


function renderDreams(containerId, config, prefix) {
  const wrap = document.getElementById(containerId);

  // If there's a loading element, clear it
  const loadingEl = wrap.querySelector('.loading-text');
  if (loadingEl) {
    wrap.innerHTML = '';
  }

  // If config is a string (JSON), parse it
  // if (typeof config === 'string') {
  //   try {
  //     config = JSON.parse(config);
  //   } catch (e) {
  //     console.error("Error parsing config:", e);
  //     wrap.innerHTML = '<div class="error-text">Error loading content</div>';
  //     return;
  //   }
  // }

  // // If config is an array of objects with get_support_config_group property
  // if (Array.isArray(config) && config[0] && config[0].get_support_config_group) {
  //   try {
  //     config = JSON.parse(config[0].get_support_config_group);
  //   } catch (e) {
  //     console.error("Error parsing get_support_config_group:", e);
  //     wrap.innerHTML = '<div class="error-text">Error loading content</div>';
  //     return;
  //   }
  // }

  // Group items by key since same key can have multiple entries with different categories
  const groupedItems = {};

  config.forEach((item, index) => {
    const key = item.key ? item.key.trim() : `item_${index}`;
    const label = item.label ? item.label.trim() : '';
     const category_id = item.category_id || null;

    // Initialize group if not exists
    if (!groupedItems[key]) {
      groupedItems[key] = {
        key: key,
        label: label,
        supports: []
      };
    }

    // Add main_category as heading if exists and not null/empty
    if (item.main_category && item.main_category.trim()) {
      // Check if this heading already exists in supports
      const headingExists = groupedItems[key].supports.some(
        s => s.type === 'heading' && s.text === item.main_category.trim()
      );

      if (!headingExists) {
        groupedItems[key].supports.push({
          type: 'heading',
          text: item.main_category.trim(),
          category_id: category_id
        });
      }
    }

    // Add all support items
    if (item.supports && Array.isArray(item.supports)) {
      item.supports.forEach(support => {
        groupedItems[key].supports.push({
          type: 'option',
          text: support.support_item ? support.support_item.trim() : '',
          support_id: support.support_id || null,
             category_id: category_id
        });
      });
    }
  });

  // Convert grouped object to array
  const groupedArray = Object.values(groupedItems);

  // Clear the container
  wrap.innerHTML = '';

  // Render each dream item
  groupedArray.forEach((dream, index) => {
    const id = prefix + '_' + dream.key + '_' + index;
    const row = document.createElement('div');
    row.className = 'dream-row';
    row.dataset.dreamKey = dream.key;

    // LINE 1: Dream + Priority
    const line1 = document.createElement('div');
    line1.className = "dream-line1";

    // Checkbox + Label
    const dreamBox = document.createElement('label');
    dreamBox.style.fontWeight = 600;
    dreamBox.innerHTML = `
      <input type="checkbox" data-key="${dream.key}"   data-category-id="${dream.category_id || ''}" class="dream-checkbox" id="chk_${id}" />
      <span style="margin-left:8px">${dream.label}</span>
    `;

    // Priority Dropdown
    const priority = document.createElement('div');
    priority.innerHTML = `
      <select class="priority small-input" data-section="${containerId}" id="prio_${id}" disabled>
        <option value="">Priority</option>
        <option value="1">1</option>
        <option value="2">2</option>
        <option value="3">3</option>
      </select>
    `;

    line1.appendChild(dreamBox);
    line1.appendChild(priority);

    // LINE 2: Support options (radio buttons)
    const line2 = document.createElement('div');
    line2.className = "dream-line2 support-radio-group";
    line2.id = `sup_${id}`;

    // Track if we need to add heading for default case
    let hasHeadings = dream.supports.some(s => s.type === 'heading');

    if (!hasHeadings && dream.supports.length > 0) {
      // Add a default heading if no headings exist
      const defaultHeading = document.createElement('div');
      defaultHeading.className = 'support-heading';
      defaultHeading.textContent = 'Support Options:';
      line2.appendChild(defaultHeading);
    }

    dream.supports.forEach((support, supIndex) => {
      if (support.type === 'heading') {
        const heading = document.createElement('div');
        heading.className = 'support-heading';
        heading.textContent = support.text;
        line2.appendChild(heading);
      } else if (support.type === 'option') {
        const label = document.createElement('label');
        label.className = 'support-radio-label';
        const radioId = `radio_${id}_${supIndex}`;
        label.innerHTML = `
          <input type="radio" 
                 name="sup_${id}" 
                 value="${support.text}" 
                 data-support-id="${support.support_id || ''}"
                    data-category-id="${support.category_id || ''}"
                 disabled> 
          ${support.text}
        `;
        line2.appendChild(label);
      }
    });

    // Add both lines to the row
    row.appendChild(line1);
    row.appendChild(line2);
    wrap.appendChild(row);
  });

  // Initialize the UI state
  const sectionId = containerId;
  enforceMaxSelections(sectionId);
  updateUsedPriorities(sectionId);
  updatePriorityOptions(sectionId);
}

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
      const category_id = chk.dataset.categoryId || null;

      // Get selected radio button value and support_id
      const radioGroup = row.querySelector('.support-radio-group');
      let selectedSupport = null;
      let selectedSupportId = null;
       let selectedCategoryId = category_id;  

      if (radioGroup) {
        const selectedRadio = radioGroup.querySelector('input[type="radio"]:checked');
        if (selectedRadio) {
          selectedSupport = selectedRadio.value;
          selectedSupportId = selectedRadio.dataset.supportId || null;
           selectedCategoryId = selectedRadio.dataset.categoryId || category_id;
        }
      }

      // Get the dream label
      let label = chk.closest('label').textContent.trim();
      label = label.replace(/^\s*/, '');

      responses.push({
        key: chk.dataset.key,
        priority: priority || null,
        support: selectedSupport,
        support_id: selectedSupportId,
        category_id: selectedCategoryId,
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
async function handleSubmit(e) {
  e.preventDefault();
  const form = document.getElementById('dreamForm');
  const age = Number(document.getElementById('age').value);

  if (isNaN(age) || age < 18 || age > 29) {
    showMessage('Applicants must be between 18 and 29 years old.', 'error');
    return false;
  }

  // Make sure we're using the correct function name
  const immediate = collectDreamResponses('immediateDreams', 'imm');
  const fiveYr = collectDreamResponses('fiveYearDreams', 'fiv');

  console.log('Immediate dreams:', immediate);
  console.log('Five year dreams:', fiveYr);

  if (immediate.length === 0 && fiveYr.length === 0) {
    showMessage('Please choose at least one dream in immediate or next-5-years sections.', 'error');
    return false;
  }

  if (!validatePriorities(immediate) || !validatePriorities(fiveYr)) {
    showMessage('Duplicate priorities found in a section. Please ensure priorities are unique (1-3) within each section.', 'error');
    return false;
  }

  const poi = document.getElementById('poi').files[0];

  if (!poi) {
    showMessage('Please upload all required documents.', 'error');
    return false;
  }

  // File size validation (5MB)
  const maxSize = 5 * 1024 * 1024;
  if (poi.size > maxSize) {
    showMessage('File size must be less than 5MB.', 'error');
    return false;
  }

  const formData = new FormData();
  const payload = {
    name: form.name.value,
    gender: form.gender.value,
    email: form.email.value,
    phone: form.phone.value,
    district: form.district.value,
    respondent_type: form.respondent_type.value,
    age: age,
    dob: form.dob.value,
    education: form.education.value,
    employmentStatus: form.employmentStatus.value,
    employmentType: form.employmentType.value || null,
    immediate_dreams: immediate,
    five_year_dreams: fiveYr,
    other_aspirations: form.otherAspirations.value || null,
  };
  formData.append('data', encryptData(payload));
  formData.append('poi', poi);

  console.log('Final payload:', payload);
  // Here you would normally send the payload to your API
  // For now, just log it

  showMessage('Submitting your dreams...', 'success');

  try {
    const response = await fetch(`${api_base_url}/v1/input_record`, {
      method: 'POST',
      headers: {
        'X-App-Key': 'TN_EKEE',
        'X-App-Name': 'TN_EKEE'
      },
      body: formData
    });

    const result = await response.json();

    if (result.success == 1) {
      // Delete draft on successful submission
      // if (loggedInPhone) {
      //   await deleteDraft(loggedInPhone);
      // }

      // Clear any saved session if needed (optional)
      // localStorage.removeItem('otp_verified');
      // localStorage.removeItem('verified_mobile');
      // localStorage.removeItem('otp_verified_timestamp');

      await Swal.fire({
        title: 'Success!',
        text: 'Your dreams have been submitted successfully.',
        icon: 'success',
        confirmButtonText: 'OK',
        background: '#ffffff',
        confirmButtonColor: '#10b981'
      }).then(() => {
        window.location.reload();
      });

      // Reset form
      document.getElementById('dreamForm').reset();
      document.getElementById('age').value = '';
      document.getElementById('immediateDreams').innerHTML = '';
      document.getElementById('fiveYearDreams').innerHTML = '';

      // Reload dreams
      fetchImmediateDreams();
      fetchFiveYearDreams();

    } else {
      throw new Error(result.message || 'Submission failed');
    }
  } catch (error) {
    console.error('Submission error:', error);
    showMessage('Submission failed. Please try again.', 'error');
  }
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