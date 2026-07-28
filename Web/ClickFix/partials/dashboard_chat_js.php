<script data-cfasync="false">
(function(){
if(window._cf_chat_loaded)return;window._cf_chat_loaded=true;

var b=document.getElementById('llm-bubble-panel');
if(!b)return;

var userLang='<?= clickfix_h((string)($user['preferred_lang']??$lang??'en')) ?>';

var profileSel=document.getElementById('llm-profile-select');
var modelOv=document.getElementById('llm-model-override');
var bearerOv=document.getElementById('llm-bearer-override');
var agentOv=document.getElementById('llm-agent-override');
var msgs=document.getElementById('llm-chat-messages');
var input=document.getElementById('llm-chat-input-textarea');
var sendBtn=document.getElementById('llm-chat-send');
var clearBtn=document.getElementById('llm-chat-clear');
var sumBtn=document.getElementById('llm-action-summarize');
var iocBtn=document.getElementById('llm-action-extract-ioc');
var gidEl=document.getElementById('llm-graph-id');
var dots=document.getElementById('llm-typing-dots');
var gid=gidEl?parseInt(gidEl.value||'0'):0;
var storeKey='cf_chat_'+gid;
var hist=[],busy=false;

function save(){try{localStorage.setItem(storeKey,JSON.stringify(hist))}catch(e){}}
function load(){try{var v=localStorage.getItem(storeKey);if(v){hist=JSON.parse(v);for(var i=0;i<hist.length;i++)addMsg(hist[i].role,hist[i].content,true)}}catch(e){}}

function typing(s){if(dots)dots.style.display=s?'flex':'none';if(msgs&&s)msgs.scrollTop=msgs.scrollHeight}

function addMsg(role,txt,restore){
  if(!msgs)return;
  var d=document.createElement('div');d.className='llm-msg '+role;
  var meta=role==='user'?'You':'AI';
  var body=txt
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
    .replace(/```(\w*)\n?([\s\S]*?)```/g,'<pre><code>$2</code></pre>')
    .replace(/`([^`]+)`/g,'<code>$1</code>')
    .replace(/^### (.+)$/gm,'<b>$1</b>').replace(/^## (.+)$/gm,'<b>$1</b>').replace(/^# (.+)$/gm,'<b>$1</b>')
    .replace(/\*\*(.+?)\*\*/g,'<b>$1</b>').replace(/\*(.+?)\*/g,'<i>$1</i>')
    .replace(/^\- (.+)$/gm,'\u2022 $1').replace(/^(\d+)\. (.+)$/gm,'$1. $2')
    .replace(/\[([^\]]+)\]\(([^)]+)\)/g,'<a href="$2" target="_blank">$1</a>')
    .replace(/\n\n/g,'<br><br>').replace(/\n/g,'<br>');
  d.innerHTML='<div class="msg-meta">'+meta+'</div><div class="msg-body">'+body+'</div>';
  msgs.appendChild(d);if(!restore)msgs.scrollTop=msgs.scrollHeight;return d;
}

async function send(msg){
  if(busy||!msg)return;
  var cmd=msg.trim().toLowerCase();
  if(cmd==='/summarize'||cmd==='/s'){if(sumBtn)sumBtn.click();return}
  if(cmd==='/iocs'||cmd==='/i'){if(iocBtn)iocBtn.click();return}
  if(cmd==='/clear'||cmd==='/c'){if(clearBtn)clearBtn.click();return}
  if(cmd==='/help'||cmd==='/h'){addMsg('assistant','Commands: /summarize \u2022 /iocs \u2022 /clear \u2022 /models \u2022 /help\n\nDefault profile is pre-selected from your Settings.');return}
  if(cmd==='/models'||cmd==='/m'){
    var pid=profileSel?parseInt(profileSel.value||'0'):0;
    if(!pid){addMsg('assistant','Select a profile first.');return}
    addMsg('user','/models');busy=true;typing(true);
    try{var r=await fetch('api/llm.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'models',profile_id:pid})});var d=await r.json();typing(false);
    if(d.status==='ok'&&d.models&&d.models.length>0){
      var list=d.models.map(function(m){return m.id}).join('\n');
      addMsg('assistant','Available models:\n'+list+'\n\nType the model name in the Model field to switch.');
    }else addMsg('assistant','No models found or API error.')}catch(e){typing(false);addMsg('assistant','[Error]')}
    busy=false;return
  }
  busy=true;if(sendBtn)sendBtn.disabled=true;
  addMsg('user',msg);typing(true);
  try{
    var pid=profileSel?parseInt(profileSel.value||'0'):0;
    var mOv=modelOv?modelOv.value.trim():'',bOv=bearerOv?bearerOv.value.trim():'',aOv=agentOv?agentOv.value.trim():'';
    var opts={};if(mOv)opts.model=mOv;if(bOv)opts.bearer_token=bOv;if(aOv)opts.user_agent=aOv;
    var body={action:'chat',profile_id:pid,graph_id:gid,messages:hist.concat([{role:'user',content:msg}]),options:opts};
    var r=await fetch('api/llm.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
    var d=await r.json();typing(false);
    if(d.status==='ok'&&d.content){hist.push({role:'user',content:msg},{role:'assistant',content:d.content});save();addMsg('assistant',d.content)}
    else addMsg('assistant','[Error: '+(d.error||d.message||'HTTP '+r.status)+']');
  }catch(e){typing(false);addMsg('assistant','[Connection error: '+e.message+']')}
  busy=false;if(sendBtn)sendBtn.disabled=false;
}

if(sendBtn)sendBtn.addEventListener('click',function(){var m=input?input.value.trim():'';if(!m)return;input.value='';send(m)});
if(input)input.addEventListener('keydown',function(e){if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();if(sendBtn)sendBtn.click()}});
if(clearBtn)clearBtn.addEventListener('click',function(){hist=[];save();if(msgs)msgs.innerHTML='';addMsg('assistant','Cleared. History reset for this investigation.')});
if(sumBtn)sumBtn.addEventListener('click',async function(){
  if(!gid)return;addMsg('user','/summarize');busy=true;typing(true);
  try{var r=await fetch('api/llm.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'summarize',graph_id:gid,profile_id:profileSel?parseInt(profileSel.value||'0'):0})});var d=await r.json();typing(false);if(d.status==='ok'&&d.content){hist.push({role:'user',content:'/summarize'},{role:'assistant',content:d.content});save();addMsg('assistant',d.content)}else addMsg('assistant','[Failed]')}catch(e){typing(false);addMsg('assistant','[Error]')}
  busy=false;
});
if(iocBtn)iocBtn.addEventListener('click',async function(){
  if(!gid)return;addMsg('user','/iocs');busy=true;typing(true);
  try{var r=await fetch('api/llm.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'extract_iocs',text:'',graph_id:gid,profile_id:profileSel?parseInt(profileSel.value||'0'):0})});var d=await r.json();typing(false);if(d.status==='ok'){var iocs='IOCs: '+((d.iocs||[]).map(function(i){return i.type+':'+i.value}).join(', ')||'none');hist.push({role:'user',content:'/iocs'},{role:'assistant',content:iocs});save();addMsg('assistant',iocs)}else addMsg('assistant','[Failed]')}catch(e){typing(false);addMsg('assistant','[Error]')}
  busy=false;
});
if(profileSel&&typeof llmProfilesData!=='undefined'){var dp=<?= $defaultProfileId ?>;if(dp>0){profileSel.value=String(dp);for(var i=0;i<llmProfilesData.length;i++)if(llmProfilesData[i].id===dp){if(modelOv&&llmProfilesData[i].model)modelOv.value=llmProfilesData[i].model;break}}profileSel.addEventListener('change',function(){var pid=parseInt(this.value||'0');if(pid>0)for(var i=0;i<llmProfilesData.length;i++)if(llmProfilesData[i].id===pid){if(modelOv&&llmProfilesData[i].model)modelOv.value=llmProfilesData[i].model;break}})};

var bubbleBtn=document.getElementById('llm-bubble-btn');
var bubbleClose=document.getElementById('llm-bubble-close');
if(bubbleBtn)bubbleBtn.addEventListener('click',function(){b.style.display='flex';bubbleBtn.style.display='none'});
if(bubbleClose)bubbleClose.addEventListener('click',function(){b.style.display='none';bubbleBtn.style.display='flex'});

(function(){var fs=null;function onfs(){var el=document.fullscreenElement||document.webkitFullscreenElement||document.mozFullScreenElement;if(el&&!fs){fs=b.style.display;b.style.display='none';if(bubbleBtn)bubbleBtn.style.display='none'}else if(!el&&fs!==null){b.style.display=fs;if(bubbleBtn&&fs==='none')bubbleBtn.style.display='flex';fs=null}}document.addEventListener('fullscreenchange',onfs);document.addEventListener('webkitfullscreenchange',onfs);document.addEventListener('mozfullscreenchange',onfs)})();

load();
})();
</script>
