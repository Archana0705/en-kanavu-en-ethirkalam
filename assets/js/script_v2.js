//do not touch
// total source and validation for ekee form

$(document).ready(function () {
  // Initialize language toggle first
  initLanguageToggle();
  
  checkExistingSession();
  initOTPFunctionality();
  fetchImmediateDreams();
  fetchFiveYearDreams();
  getDistrictDropDown();
  setupLogoutButton();
});

// Language Support Variables
let currentLang = 'en'; // 'en' or 'ta'
let dreamData = {
  immediate: null,
  fiveYear: null
};

// Language toggle function
function initLanguageToggle() {
  const langToggle = document.getElementById('langToggle');
  if (!langToggle) return;
  
  // Check for saved language preference
  const savedLang = localStorage.getItem('ekee_lang');
  if (savedLang) {
    currentLang = savedLang;
  }
  
  // Update button text based on current language
  updateLangButton();
  
  // Apply initial language
  applyLanguage();
  
  langToggle.addEventListener('click', function() {
    currentLang = currentLang === 'en' ? 'ta' : 'en';
    localStorage.setItem('ekee_lang', currentLang);
    updateLangButton();
    applyLanguage();
    renderDreamsWithCurrentLanguage();
  });
}
function translateSelectOptions() {
  const educationOptions = {
    'en': ['Select', 'Illiterate', '8th pass', 'Matriculation', 'Higher Secondary', 'Graduate', 'Post-graduate', 'Doctorate', 'Diploma holder'],
    'ta': ['தேர்ந்தெடுக்கவும்', 'படிப்பறிவில்லாதவர் ', '8ம் வகுப்பு தேர்ச்சி', 'மெட்ரிகுலேஷன்', 'மேல்நிலை', 'இளங்கலை', 'முதுகலை', 'முனைவர் பட்டம்', 'டிப்ளமோ வைத்திருப்பவர்']
  };
  
  const employmentOptions = {
    'en': ['Select', 'Employed', 'Unemployed', 'Student'],
    'ta': ['தேர்ந்தெடுக்கவும்','வேலையில் உள்ளவர்', 'வேலையில்லாதவர்', 'மாணவர்']
  };
  
  const districtOptions = {
    'en': ['Select district'],
    'ta': ['மாவட்டத்தைத் தேர்ந்தெடுக்கவும்']
  };
  const employmentTypeOptions = {
    'en': ['Select', 'Cultivator', 'Farmer', 'Agricultural Labour', 'Non-Agricultural Casual Labour', 'Self-Employed', 'Regular Salaried Worker (Private Sector)', 'Government or Public Sector Employee', 'Domestic or Household Worker', 'Transport Worker or Driver', 'Skilled or Technical Worker', 'Professionals', 'Homemaker', 'Unemployed or Seeking Work', 'Gig Worker or Delivery Partner', 'Other (Specify)'],
    'ta': ['தேர்ந்தெடுக்கவும்', 'விவசாயி','உழவர்', ' விவசாய தொழிலாளர்','விவசாயம் அல்லாத தற்காலிக தொழிலாளர்', 
      'சுயதொழில்', 'வழக்கமான சம்பளப் பணியாளர் (தனியார் துறை)', 'அரசு அல்லது பொதுத்துறை ஊழியர்',
       'வீட்டு வேலையாள்', 'போக்குவரத்து தொழிலாளர் அல்லது ஓட்டுநர்', 'திறமையான அல்லது தொழில்நுட்ப தொழிலாளர்', 
       'தொழில் வல்லுநர்கள்', 'இல்லத்தரசி', 'வேலையில்லாதவர் அல்லது வேலை தேடுபவர்', 'கிக் ஊழியர் அல்லது டெலிவரி பார்ட்னர்', 'மற்றவை (குறிப்பிடவும்)']
  };
    const respondentTypeOptions = {
    'en': ['Select Type', 'Student', 'Professional', 'Job Seeker'],
    'ta': ['வகையைத் தேர்ந்தெடுக்கவும்', 'மாணவர்', 'தொழில்முறை', 'வேலை தேடுபவர்']
  };
    const respondentSelect = document.getElementById('respondent_type');
  if (respondentSelect) {
    Array.from(respondentSelect.options).forEach((option, index) => {
      if (index < respondentTypeOptions[currentLang].length) {
        option.textContent = respondentTypeOptions[currentLang][index];
      }
    });
  }


   const genderOptions = {
    'en': ['Select gender', 'Female', 'Male', 'Other', 'Prefer not to say'],
    'ta': ['பாலினத்தைத் தேர்ந்தெடுக்கவும்', 'பெண்', 'ஆண்', 'மற்றவை', 'சொல்ல விருப்பமில்லை']
  };
  
  // ... existing translation code for other selects
  
  // Translate gender select
  const genderSelect = document.getElementById('gender');
  if (genderSelect) {
    Array.from(genderSelect.options).forEach((option, index) => {
      if (index < genderOptions[currentLang].length) {
        option.textContent = genderOptions[currentLang][index];
      }
    });
  }
  
  // Translate education select
  const educationSelect = document.getElementById('education');
  if (educationSelect) {
    Array.from(educationSelect.options).forEach((option, index) => {
      if (index < educationOptions[currentLang].length) {
        option.textContent = educationOptions[currentLang][index];
      }
    });
  }
  
  // Translate employment status
  const employmentSelect = document.getElementById('employmentStatus');
  if (employmentSelect) {
    Array.from(employmentSelect.options).forEach((option, index) => {
      if (index < employmentOptions[currentLang].length) {
        option.textContent = employmentOptions[currentLang][index];
      }
    });
  }
  
  // Translate employment type
  const employmentTypeSelect = document.getElementById('employmentType');
  if (employmentTypeSelect) {
    Array.from(employmentTypeSelect.options).forEach((option, index) => {
      if (index < employmentTypeOptions[currentLang].length) {
        option.textContent = employmentTypeOptions[currentLang][index];
      }
    });
  }
  
  // Translate district placeholder
  const districtSelect = document.getElementById('district');
  if (districtSelect && districtSelect.options.length > 0) {
    districtSelect.options[0].textContent = districtOptions[currentLang][0];
  }
}

function updateLangButton() {
  const langToggle = document.getElementById('langToggle');
  if (!langToggle) return;
  
  const langText = langToggle.querySelector('.lang-text');
  if (langText) {
    langText.textContent = currentLang === 'en' ? 'தமிழ்' : 'English';
  }
  
  // Add/remove language class to body
  document.body.classList.toggle('tamil-lang', currentLang === 'ta');
}

function applyLanguage() {
  // Update static text elements
  const elementsToTranslate = {
    // Form labels
    'label[for="name"]': { en: 'Name', ta: 'பெயர்' },
    'label[for="mobile"]': { en: 'Mobile Number *', ta: 'கைபேசி எண் *' },
    'label[for="email"]': { en: 'Email *', ta: 'மின்னஞ்சல் *' },
    'label[for="dob"]': { en: 'Date of Birth (for auto-age)', ta: 'பிறந்த தேதி (தானியங்கி வயது)' },
    'label[for="age"]': { en: 'Age *', ta: 'வயது *' },
    'label[for="poi"]': { en: 'Proof of Identity* (Ration Card Number, Driver License, School Certificate)', ta: 'அடையாள சான்று*: (ரேஷன் அட்டை எண், ஓட்டுநர் உரிமம், பள்ளி சான்றிதழ்)' },
    'label[id="gender_label"]': { en: 'Gender *', ta: 'பாலினம் *' },
    'label[for="respondent_type"]': { en: 'Respondent Type*', ta: 'பதிலளிப்பவர் வகை *' },
     '#respondent_type option[value=""]': { en: 'Select Type', ta: 'வகையைத் தேர்ந்தெடுக்கவும்' },
    'label[for="education"]': { en: 'Level of Education*', ta: 'கல்வித் தகுதி*' },
    'label[for="employmentStatus"]': { en: 'Employment Status*', ta: 'வேலை வாய்ப்பு நிலை *' },
    'label[for="employmentType"]': { en: 'Type of Employment*', ta: 'வேலைவாய்ப்பு வகை *' },
    'label[for="district"]': { en: 'District of Residence*', ta: 'வசிக்கும் மாவட்டம்*' },
    
    // '#otherAspirations + small': { en: '0 / 300 characters', ta: '0 / 300 எழுத்துகள்' },
     'label[for="employmentTypeOtherSpecify"]': { 
            en: 'Please specify:', 
            ta: 'தயவு செய்து குறிப்பிடவும்:' 
        },
     '#respondent_type option[value="Student"]': { en: 'Student', ta: 'மாணவர்' },
    '#respondent_type option[value="Professional"]': { en: 'Professional', ta: 'தொழில்முறை' },
    '#respondent_type option[value="Job Seeker"]': { en: 'Job Seeker', ta: 'வேலை தேடுபவர்' },
     'label[for="otherAspirations"]': { 
      en: 'Any other dreams to be fulfilled by 2030? (Short)', 
      ta: '2030-க்குள் நிறைவேற்ற வேண்டிய வேறு கனவுகள் உள்ளதா? (சுருக்கமாக)' 
    },
    // Placeholders
    '#name': { en: 'Enter your name', ta: 'உங்கள் பெயரை உள்ளிடவும்' },
    '#phone': { en: '+91', ta: '+91' },
    '#email': { en: 'you@example.com', ta: 'you@example.com' },
    // '#otherAspirations': { en: 'Type up to 300 characters', ta: '300 எழுத்துகள் வரை தட்டச்சு செய்யவும்' },
   
    // Gender options
    '#gender option[value=""]': { en: 'Select gender', ta: 'பாலினத்தைத் தேர்ந்தெடுக்கவும்' },
    '#gender option[value="Female"]': { en: 'Female', ta: 'பெண்' },
    '#gender option[value="Male"]': { en: 'Male', ta: 'ஆண்' },
    '#gender option[value="Other"]': { en: 'Other', ta: 'மற்றவை' },
    '#gender option[value="Prefer not to say"]': { en: 'Prefer not to say', ta: 'சொல்ல விருப்பமில்லை' },
    // '#respondent_type option[value=""]': { en: 'Select Type', ta: 'வகையைத் தேர்ந்தெடுக்கவும்' },
    // '#respondent_type option[value="Student"]': { en: 'Student', ta: 'மாணவர்' },
    // '#respondent_type option[value="Professional"]': { en: 'Professional', ta: 'தொழில்முறை' },
    // '#respondent_type option[value="Job Seeker"]': { en: 'Job Seeker', ta: 'வேலை தேடுபவர்' },
    
    // Submit button
    '#submit_form': { en: 'Submit', ta: 'சமர்ப்பிக்கவும்' },
    
    // Header
    'h1': { en: 'Enn Kanavu, Enn Ethirkalam 2030', ta: 'என் கனவு, என் எதிர்காலம் 2030' }
    // 'p.lead': { en: 'Articulating ambitions, fulfilling dreams, building a stronger Tamil Nadu', ta: 'ஆர்வங்களை வெளிப்படுத்துதல், கனவுகளை நிறைவேற்றுதல், வலுவான தமிழ்நாட்டைக் கட்டியெழுப்புதல்' }
  };
  
  // Apply translations
  for (const selector in elementsToTranslate) {
    const elements = document.querySelectorAll(selector);
    elements.forEach(element => {
      if (selector.includes('option')) {
        if (element.value === '') {
          element.textContent = elementsToTranslate[selector][currentLang];
        }
      } else {
        if (element.tagName === 'INPUT' || element.tagName === 'TEXTAREA') {
          element.placeholder = elementsToTranslate[selector][currentLang];
        } else if (selector === '#otherAspirations + small') {
          const currentText = element.textContent;
          const charCount = currentText.match(/\d+/)[0];
          element.textContent = currentLang === 'en' 
            ? `${charCount} / 300 characters`
            : `${charCount} / 300 எழுத்துகள்`;
        } else {
          element.textContent = elementsToTranslate[selector][currentLang];
        }
      }
    });
  }
  
  // Update file input label
  const poiLabel = document.querySelector('label[for="poi"]');
  if (poiLabel) {
    poiLabel.innerHTML = currentLang === 'en' 
      ? 'Proof of Identity (Ration Card Number, Driver License, School Certificate)<span>*</span>'
      : 'அடையாள சான்று*: (ரேஷன் அட்டை எண், ஓட்டுநர் உரிமம், பள்ளி சான்றிதழ்)<span>*</span>';
  }
  
  // Update section titles
  const sectionTitles = document.querySelectorAll('[data-translate-en]');
  sectionTitles.forEach(title => {
    const englishText = title.dataset.translateEn;
    const translations = {
      'Basic information': {
        en: 'Basic information',
        ta: 'அடிப்படை தகவல்கள்:'
      },
      'My Dream Dashboard': {
        en: 'My Dream Dashboard',
        ta: 'என் கனவு டாஷ்போர்டு'
      },
      '1. What is your dream that needs to be fulfilled in the immediate future?': {
        en: '1. What is your dream that needs to be fulfilled in the immediate future?',
        ta: '1. நெருங்கிய எதிர்காலத்தில் நிறைவேற வேண்டிய உங்கள் கனவு என்ன?'
      },
      '2. To fulfill your dreams by 2030, what new future-focused scheme should be introduced?': {
        en: '2. To fulfill your dreams by 2030, what new future-focused scheme should be introduced?',
        ta: '2. 2030-க்குள் உங்கள் கனவுகளை நிறைவேற்ற, எந்த புதிய எதிர்கால சார்ந்த திட்டம் அறிமுகப்படுத்தப்பட வேண்டும்?'
      }
    };
    
    if (translations[englishText]) {
      title.textContent = translations[englishText][currentLang];
    }
  });
  
  // Update notes
  const notes = document.querySelectorAll('.note');
  notes.forEach((note, index) => {
    if (index === 0) {
      note.textContent = currentLang === 'en'
        ? 'Instruction: Select up to 2 dreams. For every dream you select, choose one specific form of support you would like to receive'
        : 'வழிகாட்டுதல்: 2 கனவுகள் வரை தேர்வு செய்யவும். நீங்கள் தேர்வு செய்யும் ஒவ்வொரு கனவுக்கும், நீங்கள் பெற விரும்பும் ஒரு குறிப்பிட்ட ஆதரவை தேர்வு செய்யவும்';
    } else if (index === 1) {
      note.textContent = currentLang === 'en'
        ? '(Select up to 2 options. For every option you select, choose one specific form of support you would like to receive)'
        : '(2 விருப்பங்கள் வரை தேர்வு செய்யவும். நீங்கள் தேர்வு செய்யும் ஒவ்வொரு விருப்பத்திற்கும், நீங்கள் பெற விரும்பும் ஒரு குறிப்பிட்ட ஆதரவை தேர்வு செய்யவும்)';
    }
  });

   translateSelectOptions();
}


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
  const phoneInput = document.getElementById('phoneInput');

  phoneInput.addEventListener('input', function (e) {
    // Get current cursor position
    const cursorPos = this.selectionStart;

    // Remove all non-digits
    let newValue = this.value.replace(/\D/g, '');

    // Limit to 10 digits
    newValue = newValue.slice(0, 10);

    // Update value
    this.value = newValue;

    // Restore cursor position (adjust for removed characters)
    const removedChars = this.value.length - newValue.length;
    const newCursorPos = Math.max(0, cursorPos - removedChars);
    this.setSelectionRange(newCursorPos, newCursorPos);

    clearOTPErrors();
  });

  // OTP digits handling
  document.querySelectorAll('.otp-digit').forEach((digit, idx) => {
    digit.addEventListener('input', function () {
      // Remove non-numeric
      this.value = this.value.replace(/\D/g, '');

      // If a digit was entered, move to next field
      if (this.value && idx < 5) {
        document.querySelectorAll('.otp-digit')[idx + 1].focus();
      }

      clearOTPErrors();
    });

    // Handle backspace to move to previous field
    digit.addEventListener('keydown', function (e) {
      if (e.key === 'Backspace' || e.key === 'Delete') {
        // If current field is empty and backspace is pressed, go to previous field
        if (e.key === 'Backspace' && this.value === '' && idx > 0) {
          e.preventDefault(); // Prevent default backspace behavior
          document.querySelectorAll('.otp-digit')[idx - 1].focus();
        }
        // If delete is pressed, clear current field
        else if (e.key === 'Delete') {
          this.value = '';
        }
      }

      // Handle arrow keys for navigation
      if (e.key === 'ArrowLeft' && idx > 0) {
        document.querySelectorAll('.otp-digit')[idx - 1].focus();
      } else if (e.key === 'ArrowRight' && idx < 5) {
        document.querySelectorAll('.otp-digit')[idx + 1].focus();
      }
    });

    // Handle paste for OTP
    digit.addEventListener('paste', function (e) {
      e.preventDefault();
      const pastedData = e.clipboardData.getData('text');
      const digits = pastedData.replace(/\D/g, '').split('');

      // Fill current and next fields with pasted digits
      for (let i = 0; i < digits.length && (idx + i) < 6; i++) {
        document.querySelectorAll('.otp-digit')[idx + i].value = digits[i];
      }

      // Focus on the last filled field
      const lastFilledIdx = Math.min(idx + digits.length - 1, 5);
      document.querySelectorAll('.otp-digit')[lastFilledIdx].focus();

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
  document.getElementById('verifyOtpBtn').style.display = 'block';

  clearInterval(otpTimer);
  otpTimer = setInterval(() => {
    otpTimeLeft--;
    timerElement.textContent = otpTimeLeft;

    if (otpTimeLeft <= 0) {
      clearInterval(otpTimer);
      resendBtn.style.display = 'block';
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
      if (response.success == 1) {
        // $('#sendOtpBtn').html('<i class="fas fa-paper-plane"></i> OTP Sent');
        $('#sendOtpBtn').hide();
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
      if (response.success === 1) {
        // Check if user has existing application
        if (response.user_count > 0 && response.user) {
          // User has already submitted an application
          handleExistingApplication(response);
        } else {
          // New user or draft-only user
          handleNewUser(phone);
        }
      } else {
        // Error case
        console.error("Failed to check application status:", response.message);
        handleNewUser(phone);
      }

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

function handleExistingApplication(response) {
  const user = response.user;
  const userMapping = response.user_mapping;
  window.pendingIconName = user.icon_name || '';

  // Pre-fill basic information
  document.getElementById('name').value = user.name || '';
  document.getElementById('gender').value = user.gender || '';
  document.getElementById('email').value = user.email || '';
  document.getElementById('district').value = user.district || '';
  document.getElementById('dob').value = user.dob || '';
  document.getElementById('age').value = user.age || '';
  document.getElementById('education').value = user.education || '';
  document.getElementById('employmentStatus').value = user.employment_status || '';
  document.getElementById('employmentType').value = user.employment_type || '';
  document.getElementById('otherAspirations').value = user.otheraspirations || '';
  document.getElementById('respondent_type').value = user.respondent || '';


  // Show/hide employment type based on status
  console.log('User employment status:', user.employment_status);
  
  if (user.employment_status === 'Employed' || user.employment_status === 'வேலை செய்பவர்') {
    document.getElementById('employmentTypeWrap').style.display = 'block';
  }
  
  document.getElementById('employmentType').value = user.employment_type || '';
    
    // If employment type contains custom value (not from dropdown)
    const employmentTypeOptions = [
        'Cultivator / Farmer', 'Agricultural Labour', 'Non-Agricultural Casual Labour',
        'Self-Employed', 'Regular Salaried Worker (Private Sector)', 'Government or Public Sector Employee',
        'Domestic or Household Worker', 'Transport Worker or Driver', 'Skilled or Technical Worker',
        'Professionals', 'Homemaker', 'Unemployed or Seeking Work', 'Gig Worker or Delivery Partner',
        'Other (Specify)'
    ];
    
    const tamilEmploymentTypeOptions = [
        'விவசாயி / விவசாயத் தொழிலாளி', 'விவசாயத் தொழிலாளர்', 'விவசாயம் அல்லாத சாதாரண தொழிலாளர்',
        'சுயதொழில்', 'வழக்கமான சம்பளப் பணியாளர் (தனியார் துறை)', 'அரசு அல்லது பொதுத்துறை ஊழியர்',
        'வீட்டு வேலைக்காரர்', 'போக்குவரத்து தொழிலாளர் அல்லது ஓட்டுநர்', 'திறமையான அல்லது தொழில்நுட்ப தொழிலாளர்',
        'தொழில்முறை', 'இல்லத்தரசி', 'வேலை இல்லாத அல்லது வேலை தேடுபவர்', 'கிக் தொழிலாளர் அல்லது டெலிவரி கூட்டாளி',
        'மற்றவை (குறிப்பிடவும்)'
    ];
    
    const allOptions = [...employmentTypeOptions, ...tamilEmploymentTypeOptions];
    
    // Check if the stored value is not in the predefined options
    if (user.employment_type && !allOptions.includes(user.employment_type)) {
        // It's a custom value, show "Other (Specify)" and fill the textbox
        const otherOption = currentLang === 'ta' ? 'மற்றவை (குறிப்பிடவும்)' : 'Other (Specify)';
        document.getElementById('employmentType').value = otherOption;
        
        // Show and fill the specify textbox
        document.getElementById('employmentTypeOtherSpecifyWrap').style.display = 'block';
        document.getElementById('employmentTypeOtherSpecify').value = user.employment_type;
        document.getElementById('employmentTypeOtherSpecify').required = true;
    }
  if (user.poa_filepath || user.por_filepath) {

    $('.file_input').css('display', 'none');
    const poiFilepath = user.poa_filepath || user.por_filepath;
    const poiInput = document.getElementById('poi');
    const poiContainer = poiInput.parentElement;

    // Extract just the filename for display
    const fileName = poiFilepath.split('/').pop() || poiFilepath;

    // Create download link for existing file with icon
    const downloadLink = document.createElement('a');
    downloadLink.href = `${api_base_url}/uploads/${poiFilepath}`;
    downloadLink.target = '_blank';



    // Create icon element
    const icon = document.createElement('i');
    icon.className = 'fas fa-file-download';
    icon.style.marginRight = '8px';

    // Create text node
    const text = document.createTextNode(` Download previously uploaded file: ${fileName}`);

    // Append icon and text to link
    downloadLink.appendChild(icon);
    downloadLink.appendChild(text);

    // Style the link
    downloadLink.style.display = 'inline-flex';
    downloadLink.style.alignItems = 'center';
    downloadLink.style.marginTop = '8px';
    downloadLink.style.padding = '8px 12px';
    downloadLink.style.backgroundColor = '#f0f7ff';
    downloadLink.style.border = '1px solid #0f6cff';
    downloadLink.style.borderRadius = '6px';
    downloadLink.style.color = '#0f6cff';
    downloadLink.style.textDecoration = 'none';
    downloadLink.style.fontSize = '14px';
    downloadLink.style.transition = 'all 0.3s ease';

    // Hover effect
    downloadLink.onmouseenter = () => {
      downloadLink.style.backgroundColor = '#e0f0ff';
      downloadLink.style.transform = 'translateY(-1px)';
    };
    downloadLink.onmouseleave = () => {
      downloadLink.style.backgroundColor = '#f0f7ff';
      downloadLink.style.transform = 'translateY(0)';
    };

    // Create note about re-upload
    // const note = document.createElement('div');
    // note.innerHTML = '<small class="muted" style="margin-top: 8px; display: block;"><i class="fas fa-info-circle"></i> Note: If you want to update your POI, please upload a new file below.</small>';

    // Create container for existing file info
    const existingFileContainer = document.createElement('div');
    existingFileContainer.style.marginTop = '12px';
    existingFileContainer.style.marginBottom = '12px';
    existingFileContainer.style.padding = '12px';
    existingFileContainer.style.backgroundColor = '#f8fafc';
    existingFileContainer.style.borderRadius = '8px';
    existingFileContainer.style.border = '1px solid #e2e8f0';

    // Add title
    const title = document.createElement('div');
    title.innerHTML = '<strong><i class="fas fa-file-alt"></i> Previously Uploaded Document:</strong>';
    title.style.marginBottom = '8px';
    title.style.color = '#334155';

    existingFileContainer.appendChild(title);
    existingFileContainer.appendChild(downloadLink);
    // existingFileContainer.appendChild(note);

    // Insert the container after the file input
    poiContainer.appendChild(existingFileContainer);

    // Store filename for reference
    poiInput.dataset.existingFile = poiFilepath;

    // Make the file input optional if file already exists
    poiInput.required = false;

    // // Add a label to indicate this is for updating
    // const updateLabel = document.createElement('div');
    // updateLabel.innerHTML = '<label style="margin-top: 16px; margin-bottom: 8px; display: block; color: #4b5563;"><i class="fas fa-upload"></i> Upload new document (optional):</label>';
    // poiContainer.insertBefore(updateLabel, poiInput);

    // // Update the original label to indicate it's optional
    // const originalLabel = poiContainer.querySelector('label[for="poi"]');
    // if (originalLabel) {
    //   originalLabel.innerHTML = originalLabel.innerHTML.replace('<span>*</span>', '<span style="color: #6b7280"> (Optional - update if needed)</span>');
    // }
  }

  // Store application_id for reference
  localStorage.setItem('application_id', user.application_id || '');
  $('#submit_form').css('background', 'green').text('Application submitted').attr('disabled', true);

  // Pre-fill dreams after they are loaded
  // We'll use a flag to know when dreams are loaded
  window.dreamsLoaded = false;
  window.pendingUserMapping = userMapping;

  // Set up event listeners to pre-fill when dreams are rendered
  const observer = new MutationObserver(function (mutations) {
    mutations.forEach(function (mutation) {
      if (mutation.addedNodes.length > 0 && window.pendingUserMapping) {
        // Check if dreams containers have content
        const immediateContainer = document.getElementById('immediateDreams');
        const fiveYearContainer = document.getElementById('fiveYearDreams');

        if (immediateContainer.children.length > 1 && fiveYearContainer.children.length > 1) {
          // Dreams are loaded, pre-fill them
          prefillDreams(window.pendingUserMapping);
          window.pendingUserMapping = null;
          observer.disconnect();
        }
      }
    });
  });

  // Observe both dream containers
  observer.observe(document.getElementById('immediateDreams'), { childList: true });
  observer.observe(document.getElementById('fiveYearDreams'), { childList: true });


  if (window.pendingUserMapping) {
    prefillDreams(window.pendingUserMapping);
    window.pendingUserMapping = null;
    console.log(user.icon_name);
    // Safe check for icon_name element

    setTimeout(() => {
      if (user.icon_name) {
        document.getElementById('icon_name').value = user.icon_name;
        document.getElementById('icon_name').disabled = true;
        document.getElementById('icon_name').style.backgroundColor = '#f8fafc';
      }
    }, 1000);
  }




}

function prefillDreams(userMapping) {
  if (!userMapping || !userMapping.user || !Array.isArray(userMapping.user)) {
    console.error("Invalid user mapping data");
    return;
  }

  console.log("Pre-filling dreams with mapping:", userMapping.user);

  // Clear any existing selections first
  document.querySelectorAll('.dream-checkbox:checked').forEach(cb => {
    cb.checked = false;
    cb.dispatchEvent(new Event('change', { bubbles: true }));
  });

  // Group user selections by category_id for easier lookup
  const selectionsByCategory = {};
  userMapping.user.forEach(selection => {
    selectionsByCategory[selection.category_id] = {
      support_option: selection.support_option,
      priority: selection.priority
    };
  });

  console.log("Selections by category:", selectionsByCategory);

  // Function to find and select a dream row
  function selectDreamRow(categoryId, supportOptionId, priority) {
    // Find the checkbox with matching category_id
    const checkboxes = document.querySelectorAll(`.dream-checkbox[data-category-id="${categoryId}"]`);

    if (checkboxes.length > 0) {
      const checkbox = checkboxes[0];
      const row = checkbox.closest('.dream-row');

      // Check the checkbox
      checkbox.checked = true;
      checkbox.dispatchEvent(new Event('change', { bubbles: true }));

      // Set priority if available
      if (priority && priority > 0) {
        const prioritySelect = row.querySelector('.priority');
        if (prioritySelect) {
          prioritySelect.value = priority;
          prioritySelect.dispatchEvent(new Event('change', { bubbles: true }));
        }
      }

      // Find and select the radio button with matching support_id
      if (supportOptionId) {
        setTimeout(() => {
          const radio = row.querySelector(`input[type="radio"][data-support-id="${supportOptionId}"]`);
          if (radio) {
            radio.checked = true;
            radio.dispatchEvent(new Event('change', { bubbles: true }));
          } else {
            console.warn(`Radio button with support_id ${supportOptionId} not found in category ${categoryId}`);
          }
        }, 100);
      }

      return true;
    } else {
      console.warn(`Checkbox with category_id ${categoryId} not found`);
      return false;
    }
  }

  // Select all the user's previous choices
  let selectedCount = 0;
  userMapping.user.forEach(selection => {
    if (selection.category_id && selection.support_option) {
      const success = selectDreamRow(
        selection.category_id,
        selection.support_option,
        selection.priority
      );
      if (success) selectedCount++;
    }
  });

  console.log(`Successfully pre-filled ${selectedCount} out of ${userMapping.user.length} selections`);

  // Show message if some selections couldn't be found
  if (selectedCount < userMapping.user.length) {
    showMessage(`Loaded ${selectedCount} of your previous dream selections. Some options may have changed.`, 'info');
  } else if (selectedCount > 0) {
    showMessage('Your previous dream selections have been loaded.', 'success');
  }
}

function handleNewUser(phone) {
  // Clear any existing form data
  document.getElementById('dreamForm').reset();
  document.getElementById('phone').value = phone;
  document.getElementById('age').value = '';
  document.getElementById('employmentTypeWrap').style.display = 'none';

  // Clear any stored application_id
  localStorage.removeItem('application_id');

  // Clear dream selections
  document.querySelectorAll('.dream-checkbox:checked').forEach(cb => {
    cb.checked = false;
    cb.dispatchEvent(new Event('change', { bubbles: true }));
  });

  showMessage('Welcome! Please fill in your dreams.', 'info');
}
// Verify OTP API call
// Also update the verifyOTPRequest function to handle existing applications
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
        localStorage.setItem('user_data', JSON.stringify(response.user || {}));
        localStorage.setItem('response', JSON.stringify(response));
        localStorage.setItem('user_data_timestamp', new Date().getTime().toString());
        localStorage.setItem('user_count', response.user_count || 0);
        localStorage.setItem('application_id', response.user ? response.user.application_id : '');
        localStorage.setItem('user_mapping', JSON.stringify(response.user_mapping || {}));
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
      verifyBtn.innerHTML = '<i class="fas fa-check-circle"></i>&nbsp;Verify & Continue';
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

  // Only meetIcon radios
  if (e.target.matches('input[type="radio"]') && e.target.name.includes('meetIcon')) {

    const dreamRow = e.target.closest('.dream-row');
    if (!dreamRow) return;

    // 🔥 Always remove existing textbox first
    const existingInput = dreamRow.querySelector('.extra-input');
    if (existingInput) {
      existingInput.remove();
    }
    if (e.target.dataset.supportId) {
      const supportId = e.target.dataset.supportId;
      console.log('Support ID:', supportId);
    }


    // ➕ Add textbox ONLY for selected radio
    if (e.target.checked && e.target.dataset.supportId == '190') {
      const inputBox = document.createElement('div');
      inputBox.className = 'extra-input';
      inputBox.style.marginTop = '8px';
      inputBox.innerHTML = `
        <input type="text"
               id="icon_name"
               class="small-input" required
               placeholder="Enter Here">
      `;
      dreamRow.appendChild(inputBox);
    }
    else {
      const inputBox = dreamRow.querySelector('.extra-input');
      if (inputBox) inputBox.remove();
    }
  }

  // ❌ Remove textbox when dream checkbox is unchecked
  if (e.target.matches('.dream-checkbox')) {
    const dreamRow = e.target.closest('.dream-row');
    if (!dreamRow) return;

    if (!e.target.checked) {
      const inputBox = dreamRow.querySelector('.extra-input');
      if (inputBox) inputBox.remove();
    }
  }
});


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
function renderDreamsWithCurrentLanguage() {
  if (dreamData.immediate) {
    const immediateContainer = document.getElementById('immediateDreams');
    immediateContainer.innerHTML = '<div class="loading-text"><i class="fas fa-spinner fa-spin"></i> Loading dreams...</div>';
    
    // Re-render with current language
    renderDreams('immediateDreams', dreamData.immediate, 'imm');
  }
  
  if (dreamData.fiveYear) {
    const fiveYearContainer = document.getElementById('fiveYearDreams');
    fiveYearContainer.innerHTML = '<div class="loading-text"><i class="fas fa-spinner fa-spin"></i> Loading dreams...</div>';
    
    // Re-render with current language
    renderDreams('fiveYearDreams', dreamData.fiveYear, 'fiv');
  }
}

function renderDreams(containerId, config, prefix) {
  const wrap = document.getElementById(containerId);
  // If there's a loading element, clear it
  const loadingEl = wrap.querySelector('.loading-text');
  if (loadingEl) {
    wrap.innerHTML = '';
  }

  // Store config data for language switching
  if (containerId === 'immediateDreams') {
    dreamData.immediate = config;
  } else {
    dreamData.fiveYear = config;
  }

  // Sort dreams by order_column
  config.sort((a, b) => (a.order_column || 0) - (b.order_column || 0));

  // Group items by key since same key can have multiple entries with different categories
  const groupedItems = {};

  config.forEach((item, index) => {
    const key = item.key ? item.key.trim() : `item_${index}`;
    const label = item.label ? item.label.trim() : '';
    const tamilLabel = item.tamil_label ? item.tamil_label.trim() : '';
    const category_id = item.category_id || null;

    // Initialize group if not exists
    if (!groupedItems[key]) {
      groupedItems[key] = {
        key: key,
        label: label,
        tamil_label: tamilLabel,
        supports: []
      };
    }

    // Add main_category as heading if exists and not null/empty
    if (item.main_category && item.main_category.trim()) {
      const headingExists = groupedItems[key].supports.some(
        s => s.type === 'heading' && s.text === item.main_category.trim()
      );

      if (!headingExists) {
        groupedItems[key].supports.push({
          type: 'heading',
          text: item.main_category.trim(),
          tamil_text: item.tamil_main_category || item.main_category.trim(),
          category_id: category_id,
          main_category_order: item.main_category_order || 0
        });
      }
    }

    // Add all support items with their order
    if (item.supports && Array.isArray(item.supports)) {
      // Sort supports by order field
      const sortedSupports = item.supports.sort((a, b) => (a.order || 0) - (b.order || 0));

      sortedSupports.forEach(support => {
        groupedItems[key].supports.push({
          type: 'option',
          text: support.support_item ? support.support_item.trim() : '',
          tamil_text: support.tamil_support_item ? support.tamil_support_item.trim() : '',
          support_id: support.support_id || null,
          category_id: category_id,
          order: support.order || 0,
          main_category_order: item.main_category_order || 0
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

    // Find the first category_id from supports
    let categoryId = null;
    if (dream.supports && dream.supports.length > 0) {
      const firstSupport = dream.supports.find(s => s.category_id);
      if (firstSupport) {
        categoryId = firstSupport.category_id;
      }
    }

    const displayLabel = currentLang === 'ta' && dream.tamil_label ? dream.tamil_label : dream.label;
    
    dreamBox.innerHTML = `
      <input type="checkbox" 
             data-key="${dream.key}" 
             data-category-id="${categoryId || ''}"
             class="dream-checkbox" 
             id="chk_${id}" />
      <span style="margin-left:8px" class="dream-label" data-en="${dream.label}" data-ta="${dream.tamil_label || dream.label}">${displayLabel}</span>
    `;

    // Priority Dropdown
    const priority = document.createElement('div');
    priority.innerHTML = `
      <select class="priority small-input" data-section="${containerId}" id="prio_${id}" disabled>
        <option value="">${currentLang === 'ta' ? 'முன்னுரிமை' : 'Priority'}</option>
        <option value="1">1</option>
        <option value="2">2</option>
      </select>
    `;

    line1.appendChild(dreamBox);
    line1.appendChild(priority);

    // LINE 2: Support options (radio buttons)
    const line2 = document.createElement('div');
    line2.className = "dream-line2 support-radio-group";
    line2.id = `sup_${id}`;

    // Group supports by main_category for sorting
    const supportsByCategory = {};
    let currentCategory = null;

    dream.supports.forEach(support => {
      if (support.type === 'heading') {
        currentCategory = support.text;
        if (!supportsByCategory[currentCategory]) {
          supportsByCategory[currentCategory] = {
            order: support.main_category_order || 0,
            supports: [support]
          };
        }
      } else if (support.type === 'option') {
        if (currentCategory && supportsByCategory[currentCategory]) {
          supportsByCategory[currentCategory].supports.push(support);
        } else {
          // If no category, use a default one
          const defaultCategory = currentLang === 'ta' ? 'மற்ற விருப்பங்கள்' : 'Other Options';
          if (!supportsByCategory[defaultCategory]) {
            supportsByCategory[defaultCategory] = {
              order: 999,
              supports: []
            };
          }
          supportsByCategory[defaultCategory].supports.push(support);
        }
      }
    });

    // Sort categories by main_category_order
    const sortedCategories = Object.entries(supportsByCategory)
      .sort((a, b) => a[1].order - b[1].order);

    // Render each category
    sortedCategories.forEach(([categoryName, categoryData]) => {
      // Check if the first item is a heading
      const firstItem = categoryData.supports[0];
      if (firstItem && firstItem.type === 'heading') {
        // Add heading
        const heading = document.createElement('div');
        heading.className = 'support-heading';
        const headingText = currentLang === 'ta' && firstItem.tamil_text ? firstItem.tamil_text : firstItem.text;
        heading.textContent = headingText;
        heading.setAttribute('data-en', firstItem.text);
        heading.setAttribute('data-ta', firstItem.tamil_text || firstItem.text);
        line2.appendChild(heading);

        // Add options (skip the heading)
        const options = categoryData.supports.slice(1);
        // Sort options by order field
        options.sort((a, b) => (a.order || 0) - (b.order || 0));

        options.forEach((support, supIndex) => {
          if (support.type === 'option') {
            const label = document.createElement('label');
            label.className = 'support-radio-label';
            const radioId = `radio_${id}_${categoryName}_${supIndex}`;
            const displayText = currentLang === 'ta' && support.tamil_text ? support.tamil_text : support.text;
            
            label.innerHTML = `
              <input type="radio" 
                     name="sup_${id}" 
                     value="${support.text}" 
                     data-tamil-value="${support.tamil_text || support.text}"
                     data-support-id="${support.support_id || ''}"
                     data-category-id="${support.category_id || ''}"
                     disabled> 
              <span class="support-text" data-en="${support.text}" data-ta="${support.tamil_text || support.text}">${displayText}</span>
            `;
            line2.appendChild(label);
          }
        });
      } else {
        // No heading, just add options directly
        // Sort options by order field
        categoryData.supports.sort((a, b) => (a.order || 0) - (b.order || 0));

        categoryData.supports.forEach((support, supIndex) => {
          if (support.type === 'option') {
            const label = document.createElement('label');
            label.className = 'support-radio-label';
            const radioId = `radio_${id}_${categoryName}_${supIndex}`;
            const displayText = currentLang === 'ta' && support.tamil_text ? support.tamil_text : support.text;
            
            label.innerHTML = `
              <input type="radio" 
                     name="sup_${id}" 
                     value="${support.text}" 
                     data-tamil-value="${support.tamil_text || support.text}"
                     data-support-id="${support.support_id || ''}"
                     data-category-id="${support.category_id || ''}"
                     disabled> 
              <span class="support-text" data-en="${support.text}" data-ta="${support.tamil_text || support.text}">${displayText}</span>
            `;
            line2.appendChild(label);
          }
        });
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
        console.log(e.target.value);
        wrap.style.display = (e.target.value === 'Employed' || e.target.value === 'வேலை செய்பவர்') ? 'block' : 'none';
        
        // Hide the specify field if employment status changes
        document.getElementById('employmentTypeOtherSpecifyWrap').style.display = 'none';
        document.getElementById('employmentTypeOtherSpecify').value = '';
    }
    
    // Handle "Other (Specify)" for employment type
    if (e.target.id === 'employmentType') {
        const otherSpecifyWrap = document.getElementById('employmentTypeOtherSpecifyWrap');
        const isOtherSpecify = e.target.value === 'Other (Specify)' || 
                              (currentLang === 'ta' && e.target.value === 'மற்றவை (குறிப்பிடவும்)');
        
        if (isOtherSpecify) {
            otherSpecifyWrap.style.display = 'block';
            document.getElementById('employmentTypeOtherSpecify').required = true;
        } else {
            otherSpecifyWrap.style.display = 'none';
            document.getElementById('employmentTypeOtherSpecify').value = '';
            document.getElementById('employmentTypeOtherSpecify').required = false;
        }
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

  const immediate_support_ids = immediate.map(a => a.support_id).filter(Boolean);
  const fiveYr_support_ids = fiveYr.map(a => a.support_id).filter(Boolean);

  if (immediate_support_ids.length > 2) {
    showMessage('You can select a maximum of 2 immediate dreams.', 'error');
    return false;
  }
  if (fiveYr_support_ids.length > 2) {
    showMessage('You can select a maximum of 2 five-year dream.', 'error');
    return false;
  }

  console.log('Immediate dreams:', immediate);
  console.log('Five year dreams:', fiveYr);

  // Calculate totals
  const immediateCount = immediate.length;
  const fiveYrCount = fiveYr.length;
  const totalCount = immediateCount + fiveYrCount;

  if (totalCount === 0) {
    showMessage('Please choose at least one dream in immediate or next-5-years sections.', 'error');
    return;
  }
  if (totalCount > 5) {
    showMessage('You can select a maximum of 4 dreams.', 'error');
    return;
  }
  if (immediateCount > 3) {
    showMessage('You can select a maximum of 2 immediate dreams.', 'error');
    return;
  }
  if (fiveYrCount > 3) {
    showMessage('You can select a maximum of 2 five-year dream.', 'error');
    return;
  }

  if (immediateCount === 0 && fiveYrCount === 0) {
    showMessage('Please choose at least one dream in immediate or next-5-years sections.', 'error');
    return;
  }

  // if(immediateCount <= 2 && fiveYrCount <= 2) {
  //   showMessage('Please choose either immediate or next-5-years dreams.', 'error');
  //   return ;
  // }




  // Validation: Check priority uniqueness within each section
  if (!validatePriorities(immediate) || !validatePriorities(fiveYr)) {
    showMessage('Duplicate priorities found in a section. Please ensure priorities are unique (1-3) within each section.', 'error');
    return;
  }

  // Validation: Check if priorities are required when dreams are selected
  const immediateWithPriority = immediate.filter(dream => dream.priority).length;
  const fiveYrWithPriority = fiveYr.filter(dream => dream.priority).length;

  // If dreams are selected, they must have priorities
  if (immediateCount > 0 && immediateWithPriority !== immediateCount) {
    showMessage('Please assign priorities to all selected immediate dreams.', 'error');
    return;
  }

  if (fiveYrCount > 0 && fiveYrWithPriority !== fiveYrCount) {
    showMessage('Please assign priorities to all selected five-year dreams.', 'error');
    return;
  }

  // Validation: Check if support options are selected for each dream
  const immediateWithSupport = immediate.filter(dream => dream.support).length;
  const fiveYrWithSupport = fiveYr.filter(dream => dream.support).length;

  if (immediateCount > 0 && immediateWithSupport !== immediateCount) {
    showMessage('Please select support options for all selected immediate dreams.', 'error');
    return;
  }

  if (fiveYrCount > 0 && fiveYrWithSupport !== fiveYrCount) {
    showMessage('Please select support options for all selected five-year dreams.', 'error');
    return;
  }

  const poi = document.getElementById('poi').files[0];

  if (!poi) {
    showMessage('Please upload Proof of Identity document.', 'error');
    return;
  }

  // File size validation (5MB)
  const maxSize = 5 * 1024 * 1024;
  if (poi.size > maxSize) {
    showMessage('File size must be less than 5MB.', 'error');
    return;
  }

  // File type validation
  const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'application/pdf'];
  if (!allowedTypes.includes(poi.type)) {
    showMessage('Please upload a valid file type (JPEG, PNG, GIF, PDF).', 'error');
    return;
  }

   let employmentTypeValue = form.employmentType.value;
    
    // If "Other (Specify)" is selected, use the specify textbox value
    if (employmentTypeValue === 'Other (Specify)' || 
        (currentLang === 'ta' && employmentTypeValue === 'மற்றவை (குறிப்பிடவும்)')) {
        const specifyValue = document.getElementById('employmentTypeOtherSpecify').value.trim();
        if (!specifyValue) {
            showMessage('Please specify the employment type.', 'error');
            return false;
        }
        employmentTypeValue = specifyValue;
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
    icon_name: $('#icon_name').val() || null
  };
  formData.append('data', encryptData(payload));
  formData.append('poi', poi);

  console.log('Final payload:', payload);
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

// async function checkApplicationStatus(phone) {
//   const payload = {
//     action: "otp_operation",
//     operation: "get_user_details",
//     mobile_no: phone
//   };

//   $.ajax({
//     url: `${api_base_url}/v1/fn_otp_operation`,
//     type: "POST",
//     headers: { "X-App-Key": "TN_EKEE", "X-App-Name": "TN_EKEE" },
//     data: { data: encryptData(payload) },
//     dataType: "json",
//     success: function (response) {
//       if (response.success === 1 && response.user_count > 0 && response.user) {
//         // User has already submitted an application
//         // handleExistingApplication(response);
//       } else {
//         // New user or draft-only user
//         // handleNewUser(phone);
//       }

//       // In both cases, hide OTP modal and enable form as needed
//       hideOTPModal();
//     },
//     error: function () {
//       console.error("Failed to check application status");
//       // Fallback: treat as new user
//       handleNewUser(phone);
//       hideOTPModal();
//     }
//   });
// }

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


const textarea = document.getElementById('otherAspirations');
const charCount = document.getElementById('charCount');
const maxLength = textarea.maxLength;

textarea.addEventListener('input', function () {
  charCount.textContent = this.value.length;
});
