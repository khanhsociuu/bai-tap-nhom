(() => {
  const editor=document.getElementById('editor');
  const post=async (action,data) => {
    const response=await fetch('/api.php?action='+encodeURIComponent(action),{
      method:'POST',body:new URLSearchParams(data),headers:{'Accept':'application/json'}
    });
    const result=await response.json();
    if(!response.ok) throw new Error(result.error||'Yêu cầu thất bại');
    return result;
  };
  const get=async(action,id) => {
    const r=await fetch('/api.php?action='+action+'&id='+encodeURIComponent(id),{cache:'no-store'});
    const j=await r.json();if(!r.ok) throw new Error(j.error||'Không tải được dữ liệu');return j;
  };
  if(editor){
    const problemId=editor.dataset.problem,csrf=editor.dataset.csrf;
    const code=document.getElementById('code'),language=document.getElementById('language'),input=document.getElementById('stdin');
    const status=document.getElementById('editor-status'),output=document.getElementById('console');
    const drafts=JSON.parse(document.getElementById('drafts').textContent||'{}');
    const starter={python:'import sys\n\n# Đọc dữ liệu từ stdin\ndata = sys.stdin.read()\n',cpp:'#include <bits/stdc++.h>\nusing namespace std;\nint main() {\n    ios::sync_with_stdio(false);\n    cin.tie(nullptr);\n    return 0;\n}\n'};
    const view=()=>{code.value=drafts[language.value]??starter[language.value];};
    view();language.addEventListener('change',view);
    code.addEventListener('input',()=>{drafts[language.value]=code.value;});
    const values=()=>({csrf,problem_id:problemId,language:language.value,code:code.value});
    const saving=(text)=>status.textContent=text;
    document.getElementById('save').onclick=async()=>{
      try{saving('Đang lưu...');await post('draft',values());drafts[language.value]=code.value;saving('Đã lưu bản nháp vào PostgreSQL.');}
      catch(e){saving('Lỗi: '+e.message);}
    };
    const pause=t=>new Promise(resolve=>setTimeout(resolve,t));
    document.getElementById('try').onclick=async()=>{
      const btn=document.getElementById('try');btn.disabled=true;output.textContent='Đang chờ worker chạy thử...';
      try{
        const trial=await post('trial',{...values(),stdin:input.value});
        let current;
        for(let i=0;i<45;i++){
          current=await get('trial-status',trial.trial_id);
          if(current.status==='done'||current.status==='system_error') break;
          await pause(1000);
        }
        if(current.status==='done'||current.status==='system_error'){
          const parts=['Trạng thái: '+current.result_kind,'Thời gian: '+current.elapsed_ms+' ms'];
          if(current.stdout_data) parts.push('STDOUT:\n'+current.stdout_data);
          if(current.stderr_data) parts.push('STDERR:\n'+current.stderr_data);
          output.textContent=parts.join('\n\n');
        }else output.textContent='Chưa có kết quả. Hãy kiểm tra worker và thử xem lại.';
      }catch(e){output.textContent='Lỗi: '+e.message;}finally{btn.disabled=false;}
    };
    let requestKey=null;
    document.getElementById('submit').onclick=async()=>{
      const btn=document.getElementById('submit');btn.disabled=true;
      if(!requestKey) requestKey=crypto.randomUUID();
      try{
        saving('Đang gửi bài vào hàng đợi...');
        const result=await post('submit',{...values(),request_key:requestKey});
        location.href='/submission.php?id='+result.submission_id;
      }catch(e){saving('Lỗi: '+e.message+'. Thử lại sẽ dùng cùng mã yêu cầu.');btn.disabled=false;}
    };
  }
  const live=document.getElementById('submission-live');
  if(live){
    let seen='';
    const update=async()=>{
      try{
        const data=await get('submission-status',live.dataset.id),run=data.run;
        if(!run)return;
        live.textContent='Lần #'+run.generation+': '+run.status+(run.verdict?' — '+run.verdict+' ('+run.passed_count+'/'+run.total_count+')':'');
        const state=[run.id,run.status,run.verdict].join(':');
        if(seen && state!==seen) location.reload();
        seen=state;
      }catch(e){live.textContent='Không thể cập nhật trạng thái: '+e.message;}
    };update();setInterval(update,3000);
  }
  const rerun=document.getElementById('rejudge-form');
  if(rerun){rerun.addEventListener('submit',async e=>{
    e.preventDefault();const info=document.getElementById('rejudge-result');
    try{const result=await post('rejudge',Object.fromEntries(new FormData(rerun)));info.textContent='Tạo lần chấm #'+result.run_id;location.reload();}
    catch(err){info.textContent=err.message;}
  });}
})();
