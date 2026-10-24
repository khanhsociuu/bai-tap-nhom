(() => {
  const editor=document.getElementById('editor');
  const post=async (action,data) => {
    const response=await fetch('/api.php?action='+encodeURIComponent(action),{method:'POST',body:new URLSearchParams(data),headers:{'Accept':'application/json'}});
    const result=await response.json();if(!response.ok)throw new Error(result.error||'Yêu cầu thất bại');return result;
  };
  const get=async(action,id)=>{const r=await fetch('/api.php?action='+encodeURIComponent(action)+'&id='+encodeURIComponent(id),{cache:'no-store'});const j=await r.json();if(!r.ok)throw new Error(j.error||'Không tải được dữ liệu');return j;};
  const pause=t=>new Promise(resolve=>setTimeout(resolve,t));
  if(editor){
    const problemId=editor.dataset.problem,csrf=editor.dataset.csrf;
    const code=document.getElementById('code'),language=document.getElementById('language'),input=document.getElementById('stdin');
    const status=document.getElementById('editor-status'),output=document.getElementById('console');
    const drafts=JSON.parse(document.getElementById('drafts').textContent||'{}');
    const starter={python:'import sys\n\n# Viết lời giải tại đây\na, b = map(int, sys.stdin.readline().split())\nprint(a + b)\n',cpp:'#include <bits/stdc++.h>\nusing namespace std;\nint main() {\n    ios::sync_with_stdio(false);\n    cin.tie(nullptr);\n    // Viết lời giải tại đây\n    return 0;\n}\n'};
    let cm=null;
    const currentCode=()=>cm?cm.getValue():code.value;
    const setCode=value=>{if(cm)cm.setValue(value);else code.value=value;};
    const saving=text=>{status.textContent=text;};
    const syncDraft=()=>{drafts[language.value]=currentCode();saving('Có thay đổi chưa lưu.');};
    // Fallback textarea always works offline; upgrade to syntax-highlighted editor when CDN is available.
    if(window.CodeMirror){
      cm=CodeMirror.fromTextArea(code,{lineNumbers:true,lineWrapping:false,theme:'material-darker',mode:'python',indentUnit:4,tabSize:4,indentWithTabs:false,extraKeys:{'Ctrl-Enter':()=>document.getElementById('try').click(),'Cmd-Enter':()=>document.getElementById('try').click()}});
      cm.on('change',syncDraft);
    }else{
      code.addEventListener('input',syncDraft);
      code.addEventListener('keydown',e=>{
        if(e.key==='Tab'){e.preventDefault();const start=code.selectionStart,end=code.selectionEnd;code.setRangeText('    ',start,end,'end');syncDraft();}
        if(e.key==='Enter'&&e.ctrlKey){e.preventDefault();document.getElementById('try').click();}
      });
    }
    const switchLanguage=()=>{setCode(drafts[language.value]??starter[language.value]);document.getElementById('editor-filename').textContent=language.value==='cpp'?'main.cpp':'main.py';if(cm)cm.setOption('mode',language.value==='cpp'?'text/x-c++src':'python');saving('Sẵn sàng viết lời giải.');};
    language.addEventListener('change',switchLanguage);switchLanguage();
    const values=()=>({csrf,problem_id:problemId,language:language.value,code:currentCode()});
    const switchPane=(pane)=>{document.querySelectorAll('.console-tab').forEach(t=>{const active=t.dataset.pane===pane;t.classList.toggle('active',active);t.setAttribute('aria-selected',String(active));});document.querySelectorAll('.console-pane').forEach(el=>el.classList.toggle('active',el.id===pane));};
    document.querySelectorAll('.console-tab').forEach(tab=>tab.addEventListener('click',()=>switchPane(tab.dataset.pane)));
    document.querySelectorAll('.copy-sample').forEach(button=>button.addEventListener('click',async()=>{const el=document.getElementById(button.dataset.copy);if(!el)return;try{await navigator.clipboard.writeText(el.textContent);button.textContent='Đã sao chép ✓';}catch(e){button.textContent='Không thể sao chép';}}));
    document.getElementById('save').onclick=async()=>{try{saving('Đang lưu...');await post('draft',values());drafts[language.value]=currentCode();saving('✓ Đã lưu bản nháp vào PostgreSQL.');}catch(e){saving('Lỗi lưu nháp: '+e.message);}};
    document.getElementById('try').onclick=async()=>{
      const btn=document.getElementById('try');btn.disabled=true;output.textContent='◷ Đã gửi yêu cầu, đang chờ worker Docker...';switchPane('result-pane');
      try{
        const trial=await post('trial',{...values(),stdin:input.value});let current=null;
        for(let i=0;i<60;i++){
          current=await get('trial-status',trial.trial_id);
          if(current.status==='done'||current.status==='system_error')break;
          await pause(1000);
        }
        if(current&&(current.status==='done'||current.status==='system_error')){
          const parts=['KẾT QUẢ: '+current.result_kind,'Thời gian chạy: '+current.elapsed_ms+' ms'];
          if(current.stdout_data)parts.push('STDOUT:\n'+current.stdout_data);
          if(current.stderr_data)parts.push('STDERR:\n'+current.stderr_data);
          if(current.result_kind==='SYSTEM_ERROR')parts.push('Lỗi dịch vụ chấm: kiểm tra Docker và worker. Đây không phải lỗi bài làm.');
          output.textContent=parts.join('\n\n');
        }else output.textContent='Chưa có kết quả. Kiểm tra terminal worker Docker; bạn có thể mở lại trang sau.';
      }catch(e){output.textContent='Lỗi: '+e.message;}finally{btn.disabled=false;}
    };
    let requestKey=null;
    document.getElementById('submit').onclick=async()=>{
      const btn=document.getElementById('submit');btn.disabled=true;
      // Same request key is reused when network retries. Editing the source explicitly creates a new request.
      if(!requestKey)requestKey=crypto.randomUUID();
      try{saving('Đang gửi bài vào hàng đợi...');const result=await post('submit',{...values(),request_key:requestKey});location.href='/submission.php?id='+result.submission_id;}
      catch(e){saving('Lỗi: '+e.message+'. Thử lại giữ cùng mã yêu cầu.');btn.disabled=false;}
    };
    let submittedSnapshot=null;
    language.addEventListener('change',()=>{requestKey=null;submittedSnapshot=null;});
    const submitBtn=document.getElementById('submit');submitBtn.addEventListener('click',()=>{submittedSnapshot=currentCode();});
    const resetRequest=()=>{if(submittedSnapshot!==null && currentCode()!==submittedSnapshot)requestKey=null;};
    if(cm)cm.on('change',resetRequest);else code.addEventListener('input',resetRequest);
  }
  const live=document.getElementById('submission-live');
  if(live){let seen='';const update=async()=>{try{const data=await get('submission-status',live.dataset.id),run=data.run;if(!run)return;live.textContent='Lần #'+run.generation+': '+run.status+(run.verdict?' — '+run.verdict+' ('+run.passed_count+'/'+run.total_count+')':'');const state=[run.id,run.status,run.verdict].join(':');if(seen&&state!==seen)location.reload();seen=state;}catch(e){live.textContent='Không thể cập nhật trạng thái: '+e.message;}};update();const handle=setInterval(()=>{if(!document.hidden)update();},3000);window.addEventListener('pagehide',()=>clearInterval(handle));}
  const rerun=document.getElementById('rejudge-form');if(rerun){rerun.addEventListener('submit',async e=>{e.preventDefault();const info=document.getElementById('rejudge-result');try{const result=await post('rejudge',Object.fromEntries(new FormData(rerun)));info.textContent='Tạo lần chấm #'+result.run_id;location.reload();}catch(err){info.textContent=err.message;}});}
})();
