//do not touch
// total source and validation for ekee form
// Add these variables at the top with other declarations
const DB_NAME = 'EKEE_CACHE_DB';
const DB_VERSION = 1;
const APP_VERSION = '1.0.1';   // change this whenever deployment changes

const CACHE_REFRESH_HOURS = 1; // Cache refresh interval in hours
const STORES = {
  DROPDOWNS: 'dropdowns',
  DREAMS: 'dreams',
  METADATA: 'metadata'
};
// Get stored app version
async function getStoredAppVersion() {
  try {
    const db = await initDB();
    return new Promise((resolve) => {
      const tx = db.transaction([STORES.METADATA], 'readonly');
      const store = tx.objectStore(STORES.METADATA);
      const req = store.get('app_version');

      req.onsuccess = (e) => resolve(e.target.result?.value || null);
      req.onerror = () => resolve(null);
    });
  } catch {
    return null;
  }
}

// Save current app version
async function saveAppVersion() {
  const db = await initDB();
  return new Promise((resolve, reject) => {
    const tx = db.transaction([STORES.METADATA], 'readwrite');
    const store = tx.objectStore(STORES.METADATA);

    const req = store.put({
      key: 'app_version',
      value: APP_VERSION
    });

    req.onsuccess = () => resolve();
    req.onerror = reject;
  });
}

// Clear all object stores but keep DB
async function clearAllStores() {
  const db = await initDB();
  const storeNames = Object.values(STORES);

  return Promise.all(
    storeNames.map(storeName => {
      return new Promise((resolve, reject) => {
        const tx = db.transaction([storeName], 'readwrite');
        const store = tx.objectStore(storeName);
        const req = store.clear();
        req.onsuccess = () => resolve();
        req.onerror = reject;
      });
    })
  );
}

// Validate Version
async function validateDBVersion() {
  const storedVersion = await getStoredAppVersion();

  console.log("Stored Version:", storedVersion);
  console.log("Current Version:", APP_VERSION);

  if (!storedVersion) {
    // First time save
    await saveAppVersion();
    return;
  }

  if (storedVersion !== APP_VERSION) {
    console.warn("⚠️ Version mismatch → Refreshing cache only");

    await clearAllStores();      // 👈 only clears data
    await saveAppVersion();      // update version

    console.log("✅ Cache refreshed successfully");
  }
}


// Initialize IndexedDB
function initDB() {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open(DB_NAME, DB_VERSION);
    
    request.onerror = (event) => {
      console.error("IndexedDB error:", event.target.error);
      reject(event.target.error);
    };
    
    request.onsuccess = (event) => {
      console.log("IndexedDB initialized successfully");
      resolve(event.target.result);
    };
    
    request.onupgradeneeded = (event) => {
      const db = event.target.result;
      
      // Create object stores if they don't exist
      if (!db.objectStoreNames.contains(STORES.DROPDOWNS)) {
        db.createObjectStore(STORES.DROPDOWNS, { keyPath: 'id' });
      }
      
      if (!db.objectStoreNames.contains(STORES.DREAMS)) {
        db.createObjectStore(STORES.DREAMS, { keyPath: 'type' });
      }
      
      if (!db.objectStoreNames.contains(STORES.METADATA)) {
        db.createObjectStore(STORES.METADATA, { keyPath: 'key' });
      }
    };
  });
}

// Check if cache is stale
async function isCacheStale(key, maxAgeHours = CACHE_REFRESH_HOURS) {
  try {
    const db = await initDB();
    return new Promise((resolve, reject) => {
      const transaction = db.transaction([STORES.METADATA], 'readonly');
      const store = transaction.objectStore(STORES.METADATA);
      const request = store.get(`${key}_timestamp`);
      
      request.onsuccess = (event) => {
        const result = event.target.result;
        if (!result || !result.value) {
          resolve(true); // No timestamp, cache is stale
          return;
        }
        
        const lastUpdated = new Date(result.value);
        const now = new Date();
        const hoursDiff = (now - lastUpdated) / (1000 * 60 * 60);
        
        resolve(hoursDiff >= maxAgeHours);
      };
      
      request.onerror = (event) => {
        console.error("Error checking cache timestamp:", event.target.error);
        resolve(true); // On error, treat as stale
      };
    });
  } catch (error) {
    console.error("Error in isCacheStale:", error);
    return true;
  }
}

// Update cache timestamp
async function updateCacheTimestamp(key) {
  try {
    const db = await initDB();
    return new Promise((resolve, reject) => {
      const transaction = db.transaction([STORES.METADATA], 'readwrite');
      const store = transaction.objectStore(STORES.METADATA);
      const timestamp = new Date().toISOString();
      
      const request = store.put({
        key: `${key}_timestamp`,
        value: timestamp
      });
      
      request.onsuccess = () => {
        console.log(`Cache timestamp updated for ${key}: ${timestamp}`);
        resolve();
      };
      
      request.onerror = (event) => {
        console.error("Error updating timestamp:", event.target.error);
        reject(event.target.error);
      };
    });
  } catch (error) {
    console.error("Error updating cache timestamp:", error);
  }
}

// Save data to IndexedDB
async function saveToCache(storeName, key, data) {
  try {
    const db = await initDB();
    return new Promise((resolve, reject) => {
      const transaction = db.transaction([storeName], 'readwrite');
      const store = transaction.objectStore(storeName);
      
      const cacheData = {
        ...(storeName === STORES.DREAMS ? { type: key } : { id: key }),
        data: data,
        timestamp: new Date().toISOString()
      };
      
      const request = store.put(cacheData);
      
      request.onsuccess = () => {
        console.log(`Data saved to cache: ${storeName}/${key}`);
        resolve();
      };
      
      request.onerror = (event) => {
        console.error("Error saving to cache:", event.target.error);
        reject(event.target.error);
      };
    });
  } catch (error) {
    console.error("Error in saveToCache:", error);
  }
}

// Get data from IndexedDB
async function getFromCache(storeName, key) {
  try {
    const db = await initDB();
    return new Promise((resolve, reject) => {
      const transaction = db.transaction([storeName], 'readonly');
      const store = transaction.objectStore(storeName);
      const request = store.get(key);
      
      request.onsuccess = (event) => {
        const result = event.target.result;
        if (result && result.data) {
          console.log(`Data retrieved from cache: ${storeName}/${key}`);
          resolve(result.data);
        } else {
          resolve(null);
        }
      };
      
      request.onerror = (event) => {
        console.error("Error getting from cache:", event.target.error);
        resolve(null);
      };
    });
  } catch (error) {
    console.error("Error in getFromCache:", error);
    return null;
  }
}

// Clear specific cache
async function clearCache(storeName, key) {
  try {
    const db = await initDB();
    return new Promise((resolve, reject) => {
      const transaction = db.transaction([storeName], 'readwrite');
      const store = transaction.objectStore(storeName);
      const request = store.delete(key);
      
      request.onsuccess = () => {
        console.log(`Cache cleared: ${storeName}/${key}`);
        resolve();
      };
      
      request.onerror = (event) => {
        console.error("Error clearing cache:", event.target.error);
        reject(event.target.error);
      };
    });
  } catch (error) {
    console.error("Error clearing cache:", error);
  }
}

// Clear all cache
async function clearAllCache() {
  try {
    const db = await initDB();
    const storeNames = [STORES.DROPDOWNS, STORES.DREAMS, STORES.METADATA];
    
    for (const storeName of storeNames) {
      const transaction = db.transaction([storeName], 'readwrite');
      const store = transaction.objectStore(storeName);
      store.clear();
    }
    
    console.log("All cache cleared");
  } catch (error) {
    console.error("Error clearing all cache:", error);
  }
}
$(document).ready(async function () {
  try {
    await initDB();
    await validateDBVersion();   // 👈 important

    // continue normal flow
    initLanguageToggle();
    fetchDropdown();
    checkExistingSession();
    initOTPFunctionality();
    fetchImmediateDreams();
    fetchFiveYearDreams();
    getDistrictDropDown();
    setupLogoutButton();

  } catch (error) {
    console.error("IndexedDB init failed:", error);

    // fallback
    initLanguageToggle();
    fetchDropdown();
    checkExistingSession();
    initOTPFunctionality();
    fetchImmediateDreams();
    fetchFiveYearDreams();
    getDistrictDropDown();
    setupLogoutButton();
  }
});



const dropdownMapping = {
  1: 'gender',
  2: 'respondent_type',
  3: 'education',
  4: 'employmentStatus',
  5: 'employmentType'
};

// Store selected dreams for language switching
let selectedDreams = {
  immediate: [],
  fiveYear: []
};

async function fetchDropdown() {
  const cacheKey = 'dropdown_master';
  const isStale = await isCacheStale(cacheKey);
  
  // Try to get from cache first
  if (!isStale) {
    const cachedData = await getFromCache(STORES.DROPDOWNS, cacheKey);
    if (cachedData) {
      console.log("Using cached dropdown data");
      populateDropdowns(cachedData);
      return;
    }
  }
  
  // Fetch from API if cache is stale or empty
  console.log("Fetching fresh dropdown data from API");
  const payload = {
    action: "function_call",
    function_name: "fn_get_dropdown_master"
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
    success: async function (response) {
      try {
        const decrypted = decryptData(response.data);
        const parsed = JSON.parse(decrypted[0]['fn_get_dropdown_master']);
        console.log("Dropdown data:", parsed);
        
        // Save to cache
        await saveToCache(STORES.DROPDOWNS, cacheKey, parsed);
        await updateCacheTimestamp(cacheKey);
        
        populateDropdowns(parsed);
      } catch (e) {
        console.error("Error parsing dropdown data:", e);
      }
    },
    error: async function (xhr, status, error) {
      console.error("Error fetching dropdown data:", error);
      
      // Fallback to cache even if stale
      const cachedData = await getFromCache(STORES.DROPDOWNS, cacheKey);
      if (cachedData) {
        console.log("Using stale cache as fallback");
        populateDropdowns(cachedData);
      }
    }
  });
}

function populateDropdowns(dropdownData) {
  const dropdownMap = {
    5: 'gender',
    4: 'respondent_type',
    1: 'education',
    2: 'employmentStatus',
    3: 'employmentType'
  };

  dropdownData.forEach(category => {
    const categoryId = category.category_detail.id;
    const dropdownId = dropdownMap[categoryId];

    if (dropdownId) {
      const selectElement = document.getElementById(dropdownId);
      if (selectElement) {
        while (selectElement.options.length > 1) {
          selectElement.remove(1);
        }

        const sortedValues = category.dropdown_values.sort((a, b) => {
          if (a.order !== null && b.order !== null) {
            return a.order - b.order;
          }
          return 0;
        });

        sortedValues.forEach(value => {
          const option = document.createElement('option');
          option.value = value.id;
          option.textContent = value.English_text;
          option.setAttribute('data-tamil', value.Tamil_text);
          option.setAttribute('data-english', value.English_text);
          selectElement.appendChild(option);
        });

        updateDropdownText(selectElement);
      }
    }
  });
}

function updateDropdownText(selectElement) {
  if (!selectElement) return;

  Array.from(selectElement.options).forEach(option => {
    if (option.value) {
      if (currentLang === 'ta' && option.dataset.tamil) {
        option.textContent = option.dataset.tamil;
      } else if (currentLang === 'en' && option.dataset.english) {
        option.textContent = option.dataset.english;
      }
    }
  });
}

// Language Support Variables
let currentLang = 'en';
let dreamData = {
  immediate: null,
  fiveYear: null
};

// Language toggle function
function initLanguageToggle() {
  const langToggle = document.getElementById('langToggle');
  if (!langToggle) return;

  const savedLang = localStorage.getItem('ekee_lang');
  if (savedLang) {
    currentLang = savedLang;
  }

  updateLangButton();
  applyLanguage();

  langToggle.addEventListener('click', function () {
    // Save current selections before changing language
    if (document.getElementById('immediateDreams').children.length > 1) {
      selectedDreams.immediate = collectDreamResponses('immediateDreams', 'imm');
    }

    if (document.getElementById('fiveYearDreams').children.length > 1) {
      selectedDreams.fiveYear = collectDreamResponses('fiveYearDreams', 'fiv');
    }

    currentLang = currentLang === 'en' ? 'ta' : 'en';
    localStorage.setItem('ekee_lang', currentLang);
    updateLangButton();
    applyLanguage();
    renderDreamsWithCurrentLanguage();
  });
}

function updateLangButton() {
  const langToggle = document.getElementById('langToggle');
  if (!langToggle) return;

  const langText = langToggle.querySelector('.lang-text');
  if (langText) {
    langText.textContent = currentLang === 'en' ? 'தமிழ்' : 'English';
  }

  document.body.classList.toggle('tamil-lang', currentLang === 'ta');
}

function applyLanguage() {
  const elementsToTranslate = {
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
    'label[for="employmentTypeOtherSpecify"]': { en: 'Please specify:', ta: 'தயவு செய்து குறிப்பிடவும்:' },
    'label[for="otherAspirations"]': { en: 'Any other dreams to be fulfilled by 2030? (Short)', ta: '2030-க்குள் நிறைவேற்ற வேண்டிய வேறு கனவுகள் உள்ளதா? (சுருக்கமாக)' },
    '#name': { en: 'Enter your name', ta: 'உங்கள் பெயரை உள்ளிடவும்' },
    '#phone': { en: '+91', ta: '+91' },
    '#email': { en: 'you@example.com', ta: 'you@example.com' },
    '#submit_form': { en: 'Submit', ta: 'சமர்ப்பிக்கவும்' },
    'h1': { en: 'Enn Kanavu, Enn Ethirkalam 2030', ta: 'என் கனவு, என் எதிர்காலம் 2030' }
  };

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

  const poiLabel = document.querySelector('label[for="poi"]');
  if (poiLabel) {
    poiLabel.innerHTML = currentLang === 'en'
      ? 'Proof of Identity (Ration Card Number, Driver License, School Certificate)<span>*</span>'
      : 'அடையாள சான்று*: (ரேஷன் அட்டை எண், ஓட்டுநர் உரிமம், பள்ளி சான்றிதழ்)<span>*</span>';
  }

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

  translateDropdowns();
}

function translateDropdowns() {
  const dropdowns = [
    'gender',
    'respondent_type',
    'education',
    'employmentStatus',
    'employmentType'
  ];

  dropdowns.forEach(dropdownId => {
    const select = document.getElementById(dropdownId);
    if (select) {
      updateDropdownText(select);
    }
  });
}

async function getDistrictDropDown() {
  const cacheKey = 'districts';
  const isStale = await isCacheStale(cacheKey);
  
  // Try to get from cache first
  if (!isStale) {
    const cachedData = await getFromCache(STORES.DROPDOWNS, cacheKey);
    if (cachedData) {
      console.log("Using cached district data");
      populateDistrictDropdown(cachedData);
      return;
    }
  }
  
  // Fetch from API if cache is stale or empty
  console.log("Fetching fresh district data from API");
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
    success: async function (response) {
      if (response.success === 1) {
        // Save to cache
        await saveToCache(STORES.DROPDOWNS, cacheKey, response.data);
        await updateCacheTimestamp(cacheKey);
        
        populateDistrictDropdown(response.data);
      }
    },
    error: async function () {
      console.error('Error loading districts');
      
      // Fallback to cache
      const cachedData = await getFromCache(STORES.DROPDOWNS, cacheKey);
      if (cachedData) {
        console.log("Using cached district data as fallback");
        populateDistrictDropdown(cachedData);
      }
    }
  });
}

// Helper function to populate district dropdown
function populateDistrictDropdown(districtData) {
  const districtSelect = document.getElementById('district');
  
  // Clear existing options except the first one
  while (districtSelect.options.length > 1) {
    districtSelect.remove(1);
  }
  
  districtData.forEach(district => {
    const option = document.createElement('option');
    option.value = district.district_id;
    option.textContent = district.district_name;
    districtSelect.appendChild(option);
  });
}

function showOTPModal() {
  document.getElementById('otpModal').classList.add('show');
}

function hideOTPModal() {
  document.getElementById('otpModal').classList.remove('show');
}

let otpTimer = null;
let otpTimeLeft = 60;
let currentApplicationData = null;

function initOTPFunctionality() {
  const phoneInput = document.getElementById('phoneInput');

  phoneInput.addEventListener('input', function (e) {
    const cursorPos = this.selectionStart;
    let newValue = this.value.replace(/\D/g, '');
    newValue = newValue.slice(0, 10);
    this.value = newValue;
    const removedChars = this.value.length - newValue.length;
    const newCursorPos = Math.max(0, cursorPos - removedChars);
    this.setSelectionRange(newCursorPos, newCursorPos);
    clearOTPErrors();
  });

  document.querySelectorAll('.otp-digit').forEach((digit, idx) => {
    digit.addEventListener('input', function () {
      this.value = this.value.replace(/\D/g, '');
      if (this.value && idx < 5) {
        document.querySelectorAll('.otp-digit')[idx + 1].focus();
      }
      clearOTPErrors();
    });

    digit.addEventListener('keydown', function (e) {
      if (e.key === 'Backspace' || e.key === 'Delete') {
        if (e.key === 'Backspace' && this.value === '' && idx > 0) {
          e.preventDefault();
          document.querySelectorAll('.otp-digit')[idx - 1].focus();
        } else if (e.key === 'Delete') {
          this.value = '';
        }
      }

      if (e.key === 'ArrowLeft' && idx > 0) {
        document.querySelectorAll('.otp-digit')[idx - 1].focus();
      } else if (e.key === 'ArrowRight' && idx < 5) {
        document.querySelectorAll('.otp-digit')[idx + 1].focus();
      }
    });

    digit.addEventListener('paste', function (e) {
      e.preventDefault();
      const pastedData = e.clipboardData.getData('text');
      const digits = pastedData.replace(/\D/g, '').split('');

      for (let i = 0; i < digits.length && (idx + i) < 6; i++) {
        document.querySelectorAll('.otp-digit')[idx + i].value = digits[i];
      }

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
        if (response.user_count > 0 && response.user) {
          handleExistingApplication(response);
        } else {
          handleNewUser(phone);
        }
      } else {
        console.error("Failed to check application status:", response.message);
        handleNewUser(phone);
      }

      hideOTPModal();
    },
    error: function () {
      console.error("Failed to check application status");
      handleNewUser(phone);
      hideOTPModal();
    }
  });
}

function handleExistingApplication(response) {
  const user = response.user;
  const userMapping = response.user_mapping;
  window.pendingIconName = user.icon_name || '';

  // Fill basic form fields
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

  // Handle employment type display
  if (user.employment_status == 24 || user.employment_status == 24) {
    document.getElementById('employmentTypeWrap').style.display = 'block';
  }
  document.getElementById('employmentType').value = user.employment_type || '';

  document.getElementById('employmentTypeOtherSpecifyWrap').style.display = 'block';
  document.getElementById('employmentTypeOtherSpecify').value = user.employmenttypeotherspecify || '';
  document.getElementById('employmentTypeOtherSpecify').required = true;
  if (user.poa_filepath || user.por_filepath) {
    $('.file_input').css('display', 'none');
    const poiFilepath = user.poa_filepath || user.por_filepath;
    const poiInput = document.getElementById('poi');
    const poiContainer = poiInput.parentElement;

    const fileName = poiFilepath.split('/').pop() || poiFilepath;

    const downloadLink = document.createElement('a');
    downloadLink.href = `${api_base_url}/uploads/${poiFilepath}`;
    downloadLink.target = '_blank';

    const icon = document.createElement('i');
    icon.className = 'fas fa-file-download';
    icon.style.marginRight = '8px';

    const text = document.createTextNode(` Download previously uploaded file: ${fileName}`);

    downloadLink.appendChild(icon);
    downloadLink.appendChild(text);

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

    downloadLink.onmouseenter = () => {
      downloadLink.style.backgroundColor = '#e0f0ff';
      downloadLink.style.transform = 'translateY(-1px)';
    };
    downloadLink.onmouseleave = () => {
      downloadLink.style.backgroundColor = '#f0f7ff';
      downloadLink.style.transform = 'translateY(0)';
    };

    const existingFileContainer = document.createElement('div');
    existingFileContainer.style.marginTop = '12px';
    existingFileContainer.style.marginBottom = '12px';
    existingFileContainer.style.padding = '12px';
    existingFileContainer.style.backgroundColor = '#f8fafc';
    existingFileContainer.style.borderRadius = '8px';
    existingFileContainer.style.border = '1px solid #e2e8f0';

    const title = document.createElement('div');
    title.innerHTML = '<strong><i class="fas fa-file-alt"></i> Previously Uploaded Document:</strong>';
    title.style.marginBottom = '8px';
    title.style.color = '#334155';

    existingFileContainer.appendChild(title);
    existingFileContainer.appendChild(downloadLink);

    poiContainer.appendChild(existingFileContainer);

    poiInput.dataset.existingFile = poiFilepath;
    poiInput.required = false;
  }
  // Handle POI file display (existing code remains the same)
  // ... [keep your existing POI file display code]

  localStorage.setItem('application_id', user.application_id || '');
  $('#submit_form').css('background', 'green').text('Application submitted').attr('disabled', true);

  // Store the mapping for later use
  window.pendingUserMapping = userMapping;

  // Check if dreams are already loaded
  const immediateContainer = document.getElementById('immediateDreams');
  const fiveYearContainer = document.getElementById('fiveYearDreams');

  // If dreams are already rendered, prefill immediately
  if (immediateContainer.children.length > 1 && fiveYearContainer.children.length > 1) {
    console.log("Dreams already loaded, pre-filling now...");
    prefillDreams(window.pendingUserMapping);
    window.pendingUserMapping = null;
  } else {
    console.log("Dreams not loaded yet, will pre-fill after loading...");

    // Wait for dreams to load
    setTimeout(() => {
      if (window.pendingUserMapping) {
        console.log("Attempting delayed pre-fill...");
        prefillDreams(window.pendingUserMapping);
        window.pendingUserMapping = null;
      }
    }, 2000); // Wait 2 seconds for dreams to load
  }

  // If icon_name exists, disable the field
  setTimeout(() => {
    if (user.icon_name) {
      const iconInput = document.getElementById('icon_name');
      if (iconInput) {
        iconInput.value = user.icon_name;
        iconInput.disabled = true;
        iconInput.style.backgroundColor = '#f8fafc';
      }
    }
  }, 1000);
}

function prefillDreams(userMapping) {
  console.log("Attempting to prefill dreams with:", userMapping);

  if (!userMapping || !userMapping.user || !Array.isArray(userMapping.user)) {
    console.error("Invalid user mapping data:", userMapping);
    return;
  }

  // Wait a bit to ensure DOM is ready
  setTimeout(() => {
    console.log("Starting dream prefill process...");

    // Clear any existing selections
    document.querySelectorAll('.dream-checkbox:checked').forEach(cb => {
      cb.checked = false;
      cb.dispatchEvent(new Event('change', { bubbles: true }));
    });

    let selectedCount = 0;

    // Process each selection
    userMapping.user.forEach((selection, index) => {
      if (selection.category_id && selection.support_option) {
        console.log(`Processing selection ${index + 1}:`, selection);

        // Find the checkbox by category_id
        const checkboxes = document.querySelectorAll(`.dream-checkbox[data-category-id="${selection.category_id}"]`);

        if (checkboxes.length > 0) {
          const checkbox = checkboxes[0];
          const row = checkbox.closest('.dream-row');

          // Check the checkbox
          checkbox.checked = true;
          setTimeout(() => {
            checkbox.dispatchEvent(new Event('change', { bubbles: true }));
          }, 100);

          // Set priority after a short delay
          setTimeout(() => {
            if (selection.priority && selection.priority > 0) {
              const prioritySelect = row.querySelector('.priority');
              if (prioritySelect) {
                prioritySelect.value = selection.priority;
                prioritySelect.dispatchEvent(new Event('change', { bubbles: true }));
              }
            }
          }, 200);

          // Set support option after another delay
          setTimeout(() => {
            if (selection.support_option) {
              const radio = row.querySelector(`input[type="radio"][data-support-id="${selection.support_option}"]`);
              if (radio) {
                radio.checked = true;
                radio.dispatchEvent(new Event('change', { bubbles: true }));
                console.log(`Selected radio with support_id: ${selection.support_option}`);
                selectedCount++;
              } else {
                console.warn(`Radio button with support_id ${selection.support_option} not found`);
              }
            }
          }, 300);

        } else {
          console.warn(`No checkbox found for category_id: ${selection.category_id}`);
        }
      }
    });

    console.log(`Successfully pre-filled ${selectedCount} out of ${userMapping.user.length} selections`);

    // Force UI updates
    setTimeout(() => {
      enforceMaxSelections('immediateDreams');
      enforceMaxSelections('fiveYearDreams');
      updateUsedPriorities('immediateDreams');
      updateUsedPriorities('fiveYearDreams');
      updatePriorityOptions('immediateDreams');
      updatePriorityOptions('fiveYearDreams');

      if (selectedCount < userMapping.user.length) {
        showMessage(`Loaded ${selectedCount} of your previous dream selections.`, 'info');
      } else if (selectedCount > 0) {
        showMessage('Your previous dream selections have been loaded.', 'success');
      }
    }, 500);

  }, 500); // Initial delay
}
function handleNewUser(phone) {
  document.getElementById('dreamForm').reset();
  document.getElementById('phone').value = phone;
  document.getElementById('age').value = '';
  document.getElementById('employmentTypeWrap').style.display = 'none';

  localStorage.removeItem('application_id');

  document.querySelectorAll('.dream-checkbox:checked').forEach(cb => {
    cb.checked = false;
    cb.dispatchEvent(new Event('change', { bubbles: true }));
  });

  showMessage('Welcome! Please fill in your dreams.', 'info');
}

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

        loggedInPhone = phoneNumber;
        document.getElementById('userBadge').style.display = 'flex';
        document.getElementById('loggedInPhone').textContent = phoneNumber;

        hideOTPModal();

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

document.addEventListener('change', function (e) {
  if (e.target.matches('input[type="radio"]') && e.target.name.includes('meetIcon')) {
    const dreamRow = e.target.closest('.dream-row');
    if (!dreamRow) return;

    const existingInput = dreamRow.querySelector('.extra-input');
    if (existingInput) {
      existingInput.remove();
    }
    if (e.target.dataset.supportId) {
      const supportId = e.target.dataset.supportId;
      console.log('Support ID:', supportId);
    }

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
    } else {
      const inputBox = dreamRow.querySelector('.extra-input');
      if (inputBox) inputBox.remove();
    }
  }

  if (e.target.matches('.dream-checkbox')) {
    const dreamRow = e.target.closest('.dream-row');
    if (!dreamRow) return;

    if (!e.target.checked) {
      const inputBox = dreamRow.querySelector('.extra-input');
      if (inputBox) inputBox.remove();
    }
  }
});

async function fetchImmediateDreams() {
  const cacheKey = 'immediate_dreams';
  const isStale = await isCacheStale(cacheKey);
  
  // Try to get from cache first
  if (!isStale) {
    const cachedData = await getFromCache(STORES.DREAMS, cacheKey);
    if (cachedData) {
      console.log("Using cached immediate dreams");
      immediateDreamConfig = cachedData;
      renderDreams('immediateDreams', immediateDreamConfig, 'imm');
      return;
    }
  }
  
  // Fetch from API if cache is stale or empty
  console.log("Fetching fresh immediate dreams from API");
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
    success: async function (response) {
      try {
        const decrypted = decryptData(response.data);
        const parsed = JSON.parse(decrypted[0]['get_support_config_group']);
        immediateDreamConfig = parsed;
        
        // Save to cache
        await saveToCache(STORES.DREAMS, cacheKey, parsed);
        await updateCacheTimestamp(cacheKey);
        
        renderDreams('immediateDreams', immediateDreamConfig, 'imm');
      } catch (e) {
        console.error("Error parsing immediate dreams:", e);
        // Try cache as fallback
        const cachedData = await getFromCache(STORES.DREAMS, cacheKey);
        if (cachedData) {
          immediateDreamConfig = cachedData;
          renderDreams('immediateDreams', immediateDreamConfig, 'imm');
        } else {
          document.getElementById('immediateLoading').innerHTML =
            '<div class="loading-text">Error loading dreams. Please refresh.</div>';
        }
      }
    },
    error: async function (xhr, status, error) {
      console.error("Error fetching immediate dreams:", error);
      
      // Fallback to cache
      const cachedData = await getFromCache(STORES.DREAMS, cacheKey);
      if (cachedData) {
        console.log("Using cached immediate dreams as fallback");
        immediateDreamConfig = cachedData;
        renderDreams('immediateDreams', immediateDreamConfig, 'imm');
      } else {
        document.getElementById('immediateLoading').innerHTML =
          '<div class="loading-text">Error loading dreams. Please refresh.</div>';
      }
    }
  });
}
async function fetchFiveYearDreams() {
  const cacheKey = 'five_year_dreams';
  const isStale = await isCacheStale(cacheKey);
  
  // Try to get from cache first
  if (!isStale) {
    const cachedData = await getFromCache(STORES.DREAMS, cacheKey);
    if (cachedData) {
      console.log("Using cached five-year dreams");
      fiveYearConfig = cachedData;
      renderDreams('fiveYearDreams', fiveYearConfig, 'fiv');
      return;
    }
  }
  
  // Fetch from API if cache is stale or empty
  console.log("Fetching fresh five-year dreams from API");
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
    success: async function (response) {
      try {
        const decrypted = decryptData(response.data);
        const parsed = JSON.parse(decrypted[0]['get_support_config_group']);
        fiveYearConfig = parsed;
        
        // Save to cache
        await saveToCache(STORES.DREAMS, cacheKey, parsed);
        await updateCacheTimestamp(cacheKey);
        
        renderDreams('fiveYearDreams', fiveYearConfig, 'fiv');
      } catch (e) {
        console.error("Error parsing five-year dreams:", e);
        // Try cache as fallback
        const cachedData = await getFromCache(STORES.DREAMS, cacheKey);
        if (cachedData) {
          fiveYearConfig = cachedData;
          renderDreams('fiveYearDreams', fiveYearConfig, 'fiv');
        } else {
          document.getElementById('fiveYearLoading').innerHTML =
            '<div class="loading-text">Error loading dreams. Please refresh.</div>';
        }
      }
    },
    error: async function (xhr, status, error) {
      console.error("Error fetching five-year dreams:", error);
      
      // Fallback to cache
      const cachedData = await getFromCache(STORES.DREAMS, cacheKey);
      if (cachedData) {
        console.log("Using cached five-year dreams as fallback");
        fiveYearConfig = cachedData;
        renderDreams('fiveYearDreams', fiveYearConfig, 'fiv');
      } else {
        document.getElementById('fiveYearLoading').innerHTML =
          '<div class="loading-text">Error loading dreams. Please refresh.</div>';
      }
    }
  });
}

function renderDreamsWithCurrentLanguage() {
  // Save current selections before re-rendering
  if (document.getElementById('immediateDreams').children.length > 1) {
    selectedDreams.immediate = collectDreamResponses('immediateDreams', 'imm');
  }

  if (document.getElementById('fiveYearDreams').children.length > 1) {
    selectedDreams.fiveYear = collectDreamResponses('fiveYearDreams', 'fiv');
  }

  if (dreamData.immediate) {
    const immediateContainer = document.getElementById('immediateDreams');
    immediateContainer.innerHTML = '<div class="loading-text"><i class="fas fa-spinner fa-spin"></i> Loading dreams...</div>';
    renderDreams('immediateDreams', dreamData.immediate, 'imm');
  }

  if (dreamData.fiveYear) {
    const fiveYearContainer = document.getElementById('fiveYearDreams');
    fiveYearContainer.innerHTML = '<div class="loading-text"><i class="fas fa-spinner fa-spin"></i> Loading dreams...</div>';
    renderDreams('fiveYearDreams', dreamData.fiveYear, 'fiv');
  }
}

function renderDreams(containerId, config, prefix) {
  const wrap = document.getElementById(containerId);
  const loadingEl = wrap.querySelector('.loading-text');
  if (loadingEl) {
    wrap.innerHTML = '';
  }

  if (containerId === 'immediateDreams') {
    dreamData.immediate = config;
  } else {
    dreamData.fiveYear = config;
  }

  config.sort((a, b) => (a.order_column || 0) - (b.order_column || 0));

  const groupedItems = {};

  config.forEach((item, index) => {
    const key = item.key ? item.key.trim() : `item_${index}`;
    const label = item.label ? item.label.trim() : '';
    const tamilLabel = item.tamil_label ? item.tamil_label.trim() : '';
    const category_id = item.category_id || null;

    if (!groupedItems[key]) {
      groupedItems[key] = {
        key: key,
        label: label,
        tamil_label: tamilLabel,
        supports: []
      };
    }

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

    if (item.supports && Array.isArray(item.supports)) {
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

  const groupedArray = Object.values(groupedItems);
  wrap.innerHTML = '';

  groupedArray.forEach((dream, index) => {
    const id = prefix + '_' + dream.key + '_' + index;
    const row = document.createElement('div');
    row.className = 'dream-row';
    row.dataset.dreamKey = dream.key;

    const line1 = document.createElement('div');
    line1.className = "dream-line1";

    const dreamBox = document.createElement('label');
    dreamBox.style.fontWeight = 600;

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

    const line2 = document.createElement('div');
    line2.className = "dream-line2 support-radio-group";
    line2.id = `sup_${id}`;

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

    const sortedCategories = Object.entries(supportsByCategory)
      .sort((a, b) => a[1].order - b[1].order);

    sortedCategories.forEach(([categoryName, categoryData]) => {
      const firstItem = categoryData.supports[0];
      if (firstItem && firstItem.type === 'heading') {
        const heading = document.createElement('div');
        heading.className = 'support-heading';
        const headingText = currentLang === 'ta' && firstItem.tamil_text ? firstItem.tamil_text : firstItem.text;
        heading.textContent = headingText;
        heading.setAttribute('data-en', firstItem.text);
        heading.setAttribute('data-ta', firstItem.tamil_text || firstItem.text);
        line2.appendChild(heading);

        const options = categoryData.supports.slice(1);
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

    row.appendChild(line1);
    row.appendChild(line2);
    wrap.appendChild(row);
  });

  const sectionId = containerId;
  enforceMaxSelections(sectionId);
  updateUsedPriorities(sectionId);
  updatePriorityOptions(sectionId);

  // Restore selections after rendering
  restoreSelections(containerId);
}

function restoreSelections(containerId) {
  const dreamsToRestore = containerId === 'immediateDreams'
    ? selectedDreams.immediate
    : selectedDreams.fiveYear;

  if (dreamsToRestore.length === 0) return;

  dreamsToRestore.forEach(dream => {
    if (dream.category_id) {
      // Find the checkbox with matching category_id
      const checkboxes = document.querySelectorAll(`#${containerId} .dream-checkbox[data-category-id="${dream.category_id}"]`);

      if (checkboxes.length > 0) {
        const checkbox = checkboxes[0];
        const row = checkbox.closest('.dream-row');

        // Check the checkbox
        checkbox.checked = true;
        checkbox.dispatchEvent(new Event('change', { bubbles: true }));

        // Set priority
        if (dream.priority) {
          const prioritySelect = row.querySelector('.priority');
          if (prioritySelect) {
            prioritySelect.value = dream.priority;
            prioritySelect.dispatchEvent(new Event('change', { bubbles: true }));
          }
        }

        // Set radio button selection
        if (dream.support_id) {
          setTimeout(() => {
            const radio = row.querySelector(`input[type="radio"][data-support-id="${dream.support_id}"]`);
            if (radio) {
              radio.checked = true;
              radio.dispatchEvent(new Event('change', { bubbles: true }));
            }
          }, 100);
        }
      }
    }
  });

  // Update UI state after restoring selections
  enforceMaxSelections(containerId);
  updateUsedPriorities(containerId);
  updatePriorityOptions(containerId);
}

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
      if (!opt.value) return;

      if (usedPriorities[sectionId].has(opt.value) && opt.value !== currentVal) {
        opt.disabled = true;
        opt.style.display = 'none';
      } else {
        opt.disabled = false;
        opt.style.display = 'block';
      }
    });
  });
}

document.addEventListener('change', (e) => {
  if (e.target.classList.contains('dream-checkbox')) {
    const sectionId = e.target.closest('#immediateDreams') ? 'immediateDreams' : 'fiveYearDreams';
    enforceMaxSelections(sectionId);
  }

  if (e.target.classList.contains('priority')) {
    const sectionId = e.target.dataset.section;
    updateUsedPriorities(sectionId);
    updatePriorityOptions(sectionId);
  }

  if (e.target.classList.contains('priority') || e.target.classList.contains('support')) {
    const id = e.target.id.replace(/^(prio|sup)_/, '');
    const chk = document.getElementById('chk_' + id);
    if (e.target.value !== '') chk.checked = true;
    const sectionId = e.target.closest('#immediateDreams') ? 'immediateDreams' : 'fiveYearDreams';
    enforceMaxSelections(sectionId);
  }

  if (e.target.id === 'employmentStatus') {
    const wrap = document.getElementById('employmentTypeWrap');
    console.log(e.target.value);
    wrap.style.display = (e.target.value == 24) ? 'block' : 'none';

    document.getElementById('employmentTypeOtherSpecifyWrap').style.display = 'none';
    document.getElementById('employmentTypeOtherSpecify').value = '';
  }

  if (e.target.id === 'employmentType') {
    const otherSpecifyWrap = document.getElementById('employmentTypeOtherSpecifyWrap');
    const isOtherSpecify = e.target.value == 41;

    if (isOtherSpecify) {
      otherSpecifyWrap.style.display = 'block';
      document.getElementById('employmentTypeOtherSpecify').required = true;
    } else {
      otherSpecifyWrap.style.display = 'none';
      document.getElementById('employmentTypeOtherSpecify').value = '';
      document.getElementById('employmentTypeOtherSpecify').required = false;
    }
  }

  if (e.target.id === 'dob') {
    updateAgeFromDOB();
  }
});

function enforceMaxSelections(sectionId) {
  const container = document.getElementById(sectionId);
  const rows = container.querySelectorAll('.dream-row');
  const checkedCount = Array.from(container.querySelectorAll('.dream-checkbox')).filter(c => c.checked).length;
  const maxReached = checkedCount >= 2;

  rows.forEach(row => {
    const chk = row.querySelector('.dream-checkbox');
    const pri = row.querySelector('.priority');
    const supGroup = row.querySelector('.support-radio-group');

    if (chk.checked) {
      chk.disabled = false;
      pri.disabled = false;

      supGroup.querySelectorAll('input[type="radio"]').forEach(r => r.disabled = false);

      row.classList.remove('disabled');

    } else {
      if (maxReached) {
        chk.disabled = true;

        pri.value = '';
        pri.disabled = true;

        supGroup.querySelectorAll('input[type="radio"]').forEach(r => {
          r.checked = false;
          r.disabled = true;
        });

        row.classList.add('disabled');

      } else {
        chk.disabled = false;

        pri.value = '';
        pri.disabled = true;

        supGroup.querySelectorAll('input[type="radio"]').forEach(r => {
          r.checked = false;
          r.disabled = true;
        });

        row.classList.remove('disabled');
      }
    }
  });
}

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

  // Store selections for language switching
  if (containerId === 'immediateDreams') {
    selectedDreams.immediate = responses;
  } else {
    selectedDreams.fiveYear = responses;
  }

  return responses;
}

function validatePriorities(arr) {
  const prios = arr.map(a => a.priority).filter(Boolean);
  return prios.length === new Set(prios).size;
}

async function handleSubmit(e) {
  e.preventDefault();
  const form = document.getElementById('dreamForm');
  const age = Number(document.getElementById('age').value);

  if (isNaN(age) || age < 16 || age > 35) {
    showMessage('Applicants must be between 16 and 35 years old.', 'error');
    return false;
  }

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

  if (!validatePriorities(immediate) || !validatePriorities(fiveYr)) {
    showMessage('Duplicate priorities found in a section. Please ensure priorities are unique (1-3) within each section.', 'error');
    return;
  }

  const immediateWithPriority = immediate.filter(dream => dream.priority).length;
  const fiveYrWithPriority = fiveYr.filter(dream => dream.priority).length;

  if (immediateCount > 0 && immediateWithPriority !== immediateCount) {
    showMessage('Please assign priorities to all selected immediate dreams.', 'error');
    return;
  }

  if (fiveYrCount > 0 && fiveYrWithPriority !== fiveYrCount) {
    showMessage('Please assign priorities to all selected five-year dreams.', 'error');
    return;
  }

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

  const maxSize = 5 * 1024 * 1024;
  if (poi.size > maxSize) {
    showMessage('File size must be less than 5MB.', 'error');
    return;
  }

  const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'application/pdf'];
  if (!allowedTypes.includes(poi.type)) {
    showMessage('Please upload a valid file type (JPEG, PNG, GIF, PDF).', 'error');
    return;
  }

  let employmentTypeValue = form.employmentType.value;
  if (employmentTypeValue == 41) {
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
    employmentTypeOtherSpecify: form.employmentTypeOtherSpecify.value || null,
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

      document.getElementById('dreamForm').reset();
      document.getElementById('age').value = '';
      document.getElementById('immediateDreams').innerHTML = '';
      document.getElementById('fiveYearDreams').innerHTML = '';

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

function showMessage(msg, type = 'info') {
  const el = document.getElementById('formMessage');
  el.innerText = msg;
  el.style.color = (type === 'error') ? '#b91c1c' : '#064e3b';
  setTimeout(() => { el.innerText = ''; }, 6000);
}

async function checkExistingSession() {
  const isVerified = localStorage.getItem('otp_verified') === 'true';
  const verifiedMobile = localStorage.getItem('verified_mobile');
  const timestamp = localStorage.getItem('otp_verified_timestamp');
  const now = Date.now();

  const isSessionValid = isVerified && verifiedMobile && timestamp &&
    (now - parseInt(timestamp)) < (24 * 60 * 60 * 1000);

  if (!isSessionValid) {
    localStorage.clear();
    showOTPModal();
    return;
  }

  loggedInPhone = verifiedMobile;

  document.getElementById('userBadge').style.display = 'flex';
  document.getElementById('loggedInPhone').textContent = verifiedMobile;
  document.getElementById('logoutBtn').style.display = 'inline-block';
  $('#phone').val(verifiedMobile).attr('readonly', true);

  await checkApplicationStatus(verifiedMobile);
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
      localStorage.removeItem('otp_verified');
      localStorage.removeItem('verified_mobile');
      localStorage.removeItem('otp_verified_timestamp');
      localStorage.removeItem('application_id');

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