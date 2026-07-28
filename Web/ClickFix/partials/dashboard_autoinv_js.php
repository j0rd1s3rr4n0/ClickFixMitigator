<script data-cfasync="false">
(function(){
var panel=document.getElementById('auto-inv-panel');
if(!panel)return;

var toggle=document.getElementById('auto-inv-toggle');
var runBtn=document.getElementById('auto-inv-run');
var refreshBtn=document.getElementById('auto-inv-refresh');
var dot=document.getElementById('auto-inv-status-dot');
var list=document.getElementById('auto-inv-jobs-list');
var count=document.getElementById('auto-inv-jobs-count');

async function loadStatus(){
  try{
    var r=await fetch('api/auto_investigation.php?action=status',{credentials:'same-origin'});
    var d=await r.json();
    if(d.status==='ok'){
      if(dot)dot.className='status-dot '+(d.enabled?'on':'off');
      if(toggle)toggle.textContent=d.enabled?'Disable':'Enable';
    }
  }catch(e){}
}

async function loadJobs(){
  try{
    var r=await fetch('api/auto_investigation.php?action=jobs&limit=30',{credentials:'same-origin'});
    var d=await r.json();
    if(d.status==='ok'&&list){
      if(count)count.textContent=String(d.count||0);
      list.innerHTML=(d.jobs||[]).map(function(j){
        var cls=j.status==='running'?'running':j.status==='completed'?'completed':j.status==='failed'?'failed':'';
        return '<div class="auto-inv-job"><span class="job-id">#'+j.id+'</span><span class="job-title">'+(j.graph_title||j.report_hostname||'Job #'+j.id)+'</span><span class="job-stage '+cls+'">'+(j.status||'queued')+'</span>'+(j.report_score?'<span class="job-score">'+j.report_score+'/100</span>':'')+'</div>';
      }).join('');
    }
  }catch(e){}
}

if(toggle)toggle.addEventListener('click',async function(){
  try{
    var r=await fetch('api/auto_investigation.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'toggle'})});
    var d=await r.json();
    if(d.status==='ok'){if(toggle)toggle.textContent=d.enabled?'Disable':'Enable';if(dot)dot.className='status-dot '+(d.enabled?'on':'off')}
  }catch(e){}
});

if(runBtn)runBtn.addEventListener('click',async function(){
  runBtn.disabled=true;runBtn.textContent='Running...';
  try{
    var r=await fetch('api/auto_investigation.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'run'})});
    var d=await r.json();
    alert('Auto-investigation: '+(d.status==='ok'?'Done':'Error: '+(d.message||'unknown')));
    loadJobs();
  }catch(e){alert('Error: '+e.message)}
  runBtn.disabled=false;runBtn.textContent='Run Now';
});

if(refreshBtn)refreshBtn.addEventListener('click',function(){loadJobs();loadStatus()});

loadStatus();loadJobs();
})();
</script>
