<?php if ($loggedIn && $page === 'intel' && ($selectedInvestigation !== null || $intelComposeNew)): ?>
<div class="llm-bubble" id="llm-bubble">
  <button class="llm-bubble-btn" id="llm-bubble-btn" title="AI Assistant">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 4-7 8-7s8 3 8 7"/></svg>
  </button>
  <div class="llm-bubble-panel" id="llm-bubble-panel" style="display:none">
    <div class="llm-bubble-head">
      <strong>AI Analyst</strong>
      <div style="display:flex;gap:6px;align-items:center">
        <select id="llm-profile-select" style="padding:4px 8px;border:1px solid var(--line);border-radius:6px;background:var(--bg);color:var(--txt);font-size:.7rem;max-width:130px">
          <option value="0">Profile</option>
          <?php $defaultProfileId = clickfix_llm_default_profile_id($pdo, (int)($user['id']??0)); foreach ($llmProfiles as $lp): ?><option value="<?= (int)($lp['id']??0) ?>"<?= ((int)($lp['id']??0)===$defaultProfileId)?' selected':'' ?>><?= clickfix_h((string)($lp['label']??'Profile')) ?></option><?php endforeach; ?>
        </select>
        <button type="button" class="llm-bubble-close" id="llm-bubble-close">&times;</button>
      </div>
    </div>
    <div class="llm-bubble-config" id="llm-bubble-config" style="display:none;padding:6px 10px;border-bottom:1px solid var(--line);display:flex;gap:4px;flex-wrap:wrap">
      <input type="text" id="llm-model-override" placeholder="Model" style="flex:1;min-width:80px;padding:4px 8px;border:1px solid var(--line);border-radius:6px;background:var(--bg);color:var(--txt);font-size:.7rem" list="llm-models-datalist">
      <datalist id="llm-models-datalist"></datalist>
      <input type="text" id="llm-bearer-override" placeholder="Bearer" style="flex:1;min-width:80px;padding:4px 8px;border:1px solid var(--line);border-radius:6px;background:var(--bg);color:var(--txt);font-size:.7rem">
      <input type="text" id="llm-agent-override" placeholder="UA" style="width:60px;padding:4px 8px;border:1px solid var(--line);border-radius:6px;background:var(--bg);color:var(--txt);font-size:.7rem">
      <input type="hidden" id="llm-graph-id" value="<?= (int)($selectedInvestigation['id']??0) ?>">
    </div>
    <div class="llm-bubble-msgs" id="llm-chat-messages">
      <div class="llm-msg assistant"><div class="msg-body">Hi! I'm your AI analyst. Type /help for commands.</div></div>
    </div>
    <div class="llm-bubble-input">
      <textarea id="llm-chat-input-textarea" placeholder="Type /summarize, /iocs, /clear..." rows="1"></textarea>
      <button type="button" id="llm-chat-send"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M2 21l21-9L2 3v7l15 2-15 2v7z"/></svg></button>
    </div>
    <div class="llm-bubble-actions">
      <button type="button" class="btn btn-sm" id="llm-action-summarize">/summarize</button>
      <button type="button" class="btn btn-sm" id="llm-action-extract-ioc">/iocs</button>
      <button type="button" class="btn btn-sm" id="llm-chat-clear">/clear</button>
    </div>
    <div class="llm-typing-dots" id="llm-typing-dots" style="display:none">
      <span></span><span></span><span></span>
    </div>
  </div>
</div>

<style>
.llm-bubble{position:fixed;bottom:20px;right:20px;z-index:9999;font-family:var(--font-main)}
.llm-bubble-btn{width:50px;height:50px;border-radius:50%;background:var(--brand);color:#050d18;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 20px rgba(0,0,0,.4);transition:all .2s;font-size:1.2rem}
.llm-bubble-btn:hover{transform:scale(1.08);box-shadow:0 6px 28px rgba(111,246,255,.3)}
.llm-bubble-panel{position:absolute;bottom:60px;right:0;width:380px;max-height:520px;background:var(--bg-layer);border:1px solid var(--line);border-radius:16px;overflow:hidden;box-shadow:0 12px 50px rgba(0,0,0,.5);display:flex;flex-direction:column;animation:llmSlideUp .2s ease-out}
@keyframes llmSlideUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
.llm-bubble-head{display:flex;justify-content:space-between;align-items:center;padding:10px 14px;background:var(--bg-soft);border-bottom:1px solid var(--line)}
.llm-bubble-head strong{font-size:.85rem}
.llm-bubble-close{background:none;border:none;color:var(--mut);font-size:1.2rem;cursor:pointer;padding:0 4px;line-height:1}
.llm-bubble-close:hover{color:var(--txt)}
.llm-bubble-config{display:none;padding:6px 10px;border-bottom:1px solid var(--line);gap:4px;flex-wrap:wrap}
.llm-bubble-msgs{flex:1;overflow-y:auto;padding:12px;min-height:160px;max-height:280px;display:flex;flex-direction:column;gap:8px}
.llm-bubble-msgs .llm-msg{max-width:90%;padding:8px 12px;border-radius:12px;font-size:.78rem;line-height:1.4}
.llm-bubble-msgs .llm-msg.user{align-self:flex-end;background:var(--brand);color:#050d18;border-bottom-right-radius:4px}
.llm-bubble-msgs .llm-msg.assistant{align-self:flex-start;background:var(--bg-soft);color:var(--txt);border:1px solid var(--line);border-bottom-left-radius:4px}
.llm-bubble-msgs .msg-meta{font-size:.6rem;opacity:.5;margin-bottom:2px}
.llm-bubble-input{display:flex;gap:6px;padding:8px 10px;border-top:1px solid var(--line);align-items:flex-end}
.llm-bubble-input textarea{flex:1;width:90%;resize:none;padding:7px 10px;border:1px solid var(--line);border-radius:10px;background:var(--bg);color:var(--txt);font-family:inherit;font-size:.76rem;min-height:34px;max-height:80px;line-height:1.3}
.llm-bubble-input button{width:10%;min-width:36px;height:34px;padding:0;border-radius:50%;background:var(--brand);border:none;color:#050d18;cursor:pointer;flex-shrink:0;display:flex;align-items:center;justify-content:center;transition:all .15s}
.llm-bubble-input button:hover{filter:brightness(1.15)}
.llm-bubble-input button:disabled{opacity:.4;cursor:not-allowed}
.llm-typing-dots{display:flex;gap:5px;padding:6px 12px;align-self:flex-start}
.llm-typing-dots span{width:7px;height:7px;background:var(--brand);border-radius:50%;animation:llmDot 1.2s infinite}
.llm-typing-dots span:nth-child(2){animation-delay:.15s}
.llm-typing-dots span:nth-child(3){animation-delay:.3s}
@keyframes llmDot{0%,80%,100%{opacity:.3;transform:scale(.8)}40%{opacity:1;transform:scale(1.2)}}
.llm-bubble-actions{display:flex;gap:6px;padding:6px 10px;border-top:1px solid var(--line);justify-content:center}
.llm-bubble-actions .btn{font-size:.68rem;padding:4px 10px}
@media(max-width:440px){.llm-bubble-panel{width:calc(100vw - 32px);right:-10px}}
</style>
<?php endif; ?>
