
const immediateDreamConfig = [
  {key:'meetIcon',label:'Meet an Icon - Get inspired by achievers', supports:['Open (Keep option)']},
  {key:'techTools',label:'Technology & tools to connect online', supports:['Laptop for final-year students','Data pack','Free AI subscription','Online certification courses support']},
  {key:'exploreLearn',label:'Explore & learn', supports:['ISRO visit','Industry visit (e.g., Hyundai)','Cultural heritage trip','Adventure trip','Factory and technology hub tour']},
  {key:'finance',label:'Financial Literacy & Opportunities', supports:['Personal Finance Workshops','Budgeting & Saving Toolkit','Investment Awareness Sessions','Access to Youth Credit Schemes','Entrepreneurship Finance Training']},
  {key:'trainingJobs',label:'Get training for real jobs', supports:['Career Counselling','Vocational Training','Factory Apprenticeship','Internship Opportunities','Entrepreneurship Bootcamp']},
  {key:'express',label:'Express yourself, showcase creativity', supports:['Content creation starter kit - microphone','Tripod stand for video','LED ring light','Video editing software access']},
  {key:'sports',label:'Get fit, compete and play sports', supports:['Sports Kit - Cricket','Sports Kit - Football','Sports Kit - Volleyball','Access to Coaching Camps','Access to training grounds','Access to tournaments']},
  {key:'mentorship',label:'Mentorship — Learn from success stories', supports:['Motivational event passes','Career advice','Leadership talk series','Industry mentorship','Shadowing opportunities']},
  {key:'community',label:'Community Engagement', supports:['District Youth Corps volunteering','TN Youth Media Lab participation','Youth Leadership Internship with Government']},
  {key:'global',label:'Global & Cultural Exchange', supports:['Learning foreign languages','International Internship Opportunities','Cultural Immersion Programs','Exchange Readiness Workshops','Soft-skill enhancement for global careers']}

];

const fiveYearConfig = [
  {key:'higherEdu',label:'Pursue Higher Education', supports:['Counselling support','Scholarship Support','Hostel / Accommodation Support','Global learning exposure']},
  {key:'upskill',label:'Upskilling & Market-Ready Skills', supports:['AI/Data Analytics certification','Coding bootcamp','Vocational skill training','Communication & soft-skills program']},
  {key:'employment',label:'Meaningful Employment', supports:['Job Mela','Apprenticeship','Startup company employment','Remote or gig-work opportunity']},
  {key:'selfEmployment',label:'Self-Employment & Business Growth', supports:['Startup seed grant','Business mentorship','Incubation centre support','Market linkage & branding support','Women-led business funding']},
  {key:'agro',label:'Promoting Agro-based Industries', supports:['Solar-powered farming setup','Organic farming initiatives','Agro-processing & value addition units','Floriculture & nursery development']},
  {key:'transport',label:'Smooth & Safe Transport', supports:['Subsidized transport from college to Home District (on special occasions)','Increased bus frequency','Provision of Bus Marshalls']},
  {key:'sportsEx',label:'Sports Excellence', supports:['Access to training grounds','Access to coaching centres','Upgrade sports hostels']},
  {key:'health',label:'Health, Fitness & Well-being', supports:['Fitness & sports training','Annual health check-up support','Mental health counselling','Nutrition & lifestyle guidance']},
  {key:'social',label:'Social Service & Youth Leadership', supports:['Volunteer network participation','Climate/environment action program','Anti-drugs action program','Youth leadership council membership']},
  {key:'globalExp',label:'Global Exposure', supports:['Student exchange program','Teaching a foreign language','Foreign fellowship','International internship']}
];

function renderDreams(containerId, config, prefix){
  const wrap = document.getElementById(containerId);
  wrap.innerHTML = '';
  config.forEach(d => {
    const dreamDiv = document.createElement('div');
    dreamDiv.className = 'dream-row';
    dreamDiv.innerHTML = `
      <div class="dream-heading" style="font-weight:600;margin-top:10px">${d.label}</div>
      <div class="support-options" style="margin-left:16px;margin-top:6px">
        ${d.supports.map((s,i)=>`
          <label style="display:block;margin-bottom:4px">
            <input type="checkbox" class="support-checkbox" data-dream="${d.key}" value="${s}" />
            ${s}
          </label>`).join('')}
      </div>`;
    wrap.appendChild(dreamDiv);
  });
}

renderDreams('immediateDreams', immediateDreamConfig, 'imm');
renderDreams('fiveYearDreams', fiveYearConfig, 'fiv');

// limit total selections to 3
// limit selections: 3 per section
document.addEventListener('change', (e)=>{
  if(e.target.classList.contains('support-checkbox')){
    const container = e.target.closest('#immediateDreams, #fiveYearDreams');
    if(!container) return;

    const allBoxes = container.querySelectorAll('.support-checkbox');
    const checked = Array.from(allBoxes).filter(c => c.checked);
    const limit = 3;

    if(checked.length >= limit){
      allBoxes.forEach(c => {
        const label = c.closest('label');
        if(!c.checked){
          c.disabled = true;
          if(label) label.style.color = '#c9c9c9';
        } else {
          if(label) label.style.color = ''; // keep selected labels normal
        }
      });
    } else {
      allBoxes.forEach(c => {
        c.disabled = false;
        const label = c.closest('label');
        if(label) label.style.color = '';
      });
    }
  }
});

function collectDreamResponses(){
  const checked = document.querySelectorAll('.support-checkbox:checked');
  const responses = [];
  checked.forEach(cb=>{
    responses.push({dream:cb.dataset.dream, support:cb.value});
  });
  return responses;
}

function showMessage(msg,type='info'){
  const el = document.getElementById('formMessage');
  el.innerText = msg;
  el.style.color = (type==='error')? '#b91c1c':'#064e3b';
  setTimeout(()=>{ el.innerText=''; },6000);
}

document.querySelector('form').addEventListener('submit', e=>{
  e.preventDefault();
  const selected = collectDreamResponses();
  if(selected.length===0){
    showMessage('Please select at least one support option.','error');
    return;
  }
  const data = {
    immediateDreams: selected.filter(s=> immediateDreamConfig.some(d=>d.key===s.dream)),
    fiveYearDreams: selected.filter(s=> fiveYearConfig.some(d=>d.key===s.dream)),
    otherAspirations: document.getElementById('otherAspirations').value || null,
    timestamp: new Date().toISOString()
  };
  const blob = new Blob([JSON.stringify(data,null,2)],{type:'application/json'});
  const url = URL.createObjectURL(blob);
  const a=document.createElement('a');a.href=url;a.download='dreams.json';a.click();
  showMessage('Form submitted and JSON downloaded.','success');
});

function saveDraft(){
  const data = {
    selections: collectDreamResponses(),
    otherAspirations: document.getElementById('otherAspirations').value
  };
  const blob = new Blob([JSON.stringify(data,null,2)],{type:'application/json'});
  const url = URL.createObjectURL(blob);
  const a=document.createElement('a');a.href=url;a.download='dreams_draft.json';a.click();
  showMessage('Draft saved.','success');
}




// tab switches


// document.querySelectorAll('.tab-buttons button').forEach(btn => {
//   btn.addEventListener('click', () => {
//     document.querySelectorAll('.tab-buttons button').forEach(b => b.classList.remove('active'));
//     document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
//     btn.classList.add('active');
//     document.getElementById(btn.dataset.tab).classList.add('active');
//   });
// });


// document.addEventListener('DOMContentLoaded', function(){
//   const sections = document.querySelectorAll('form#dreamForm .tab-section');
//   let current = 0;

//   function showSection(index) {
//     sections.forEach((s,i)=> s.classList.toggle('active', i===index));
//   }

//   function createNavButtons(){
//     sections.forEach((section, i) => {
//       if (i === sections.length-1) return; // last section already has buttons
//       const nav = document.createElement('div');
//       nav.className = 'tab-nav';
//       if (i > 0) {
//         const back = document.createElement('button');
//         back.type = 'button';
//         back.className = 'btn ghost';
//         back.textContent = '← Back';
//         back.onclick = () => { current--; showSection(current); };
//         nav.appendChild(back);
//       }

//       const next = document.createElement('button');
//       next.type = 'button';
//       next.className = 'btn';
//       next.textContent = 'Next →';
//       next.onclick = () => {
//         // Optionally validate fields in current section
//         current++;
//         if (current < sections.length) showSection(current);
//       };
//       nav.appendChild(next);

//       section.appendChild(nav);
//     });
//   }

//   createNavButtons();
//   showSection(current);
// });


// Handle step switching
const steps = document.querySelectorAll('.step');
const contents = document.querySelectorAll('.tab-content');

steps.forEach(step => {
  step.addEventListener('click', () => {
    const stepNum = parseInt(step.dataset.step);

    // Update active state for steps
    steps.forEach((s, i) => {
      s.classList.remove('active');
      s.classList.toggle('completed', i < stepNum - 1);
    });
    step.classList.add('active');

    // Update connectors
    document.querySelectorAll('.connector').forEach((con, i) => {
      con.style.backgroundColor = (i < stepNum - 1) ? '#22c55e' : '#d1d5db';
    });

    // Switch content
    contents.forEach(c => c.classList.remove('active'));
    document.getElementById(`step-${stepNum}`).classList.add('active');
  });
});