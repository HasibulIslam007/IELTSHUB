import { useEffect,useRef,useState,useCallback } from 'react';
import type { Attempt,Answers } from '../../../shared/api';
import { api,ApiError,key,isDone } from './api';
type Draft={answers:Answers;flags:string[];notes:Record<string,string>;highlights:Record<string,string>;revision:number;request_key?:string};
export function useAttempt(initial:Attempt){
 const storageKey=`hub-draft:${initial.user_id}:${initial.id}`;
 const [attempt,setAttempt]=useState(initial);const [status,setStatus]=useState('Saved');const [error,setError]=useState<unknown>();const [conflict,setConflict]=useState(false);
 const current=useRef(initial);const dirty=useRef(false);const generation=useRef(0);const inFlight=useRef<Promise<Attempt>|null>(null);const pending=useRef<Draft|null>(null);const blocked=useRef(false);
 const persist=useCallback(()=>{const a=current.current;try{localStorage.setItem(storageKey,JSON.stringify({answers:a.answers,flags:a.flags,notes:a.notes,highlights:a.highlights,revision:a.revision,...(pending.current?{pending:pending.current}:{})}));}catch{setError(new Error('Local draft storage is unavailable. Keep this page open until the server confirms Saved.'));}},[storageKey]);
 useEffect(()=>{try{const raw=localStorage.getItem(storageKey);if(raw&&!isDone(initial.status)){const draft=JSON.parse(raw);if(draft.revision===initial.revision||draft.pending){current.current={...initial,...draft};pending.current=draft.pending??null;dirty.current=true;setAttempt(current.current);setStatus('Recovered local draft');}else{setConflict(true);blocked.current=true;setError(new Error('A local draft differs from the saved server version. Download it before loading the server version.'));}}}catch{setError(new Error('Could not recover the local draft. The server copy is loaded.'));}},[]);
 const flush=useCallback(async():Promise<Attempt>=>{
  if(inFlight.current)return inFlight.current;
  if(!dirty.current||blocked.current||current.current.status!=='active')return current.current;
  const counter=generation.current;const a=current.current;
  const body=pending.current??{request_key:key(),revision:a.revision,answers:Object.fromEntries(Object.entries(a.answers).filter(([id])=>a.content.sections.flatMap(s=>s.questions).find(q=>q.id===id)?.type!=='speaking')),flags:a.flags,notes:a.notes,highlights:a.highlights};pending.current=body;persist();setStatus(navigator.onLine?'Saving…':'Offline · saved on this device');
  const promise=api<Attempt>(`/attempts/${a.id}`,'PUT',body).then(saved=>{
   pending.current=null;
   const latest=current.current;
   const latestAnswers=Object.fromEntries(Object.entries(latest.answers).filter(([id])=>latest.content.sections.flatMap(s=>s.questions).find(q=>q.id===id)?.type!=='speaking'));
   const changed=generation.current!==counter||JSON.stringify([latestAnswers,latest.flags,latest.notes,latest.highlights])!==JSON.stringify([body.answers,body.flags,body.notes,body.highlights]);
   current.current=changed?{...saved,answers:current.current.answers,flags:current.current.flags,notes:current.current.notes,highlights:current.current.highlights}:saved;
   dirty.current=changed;setAttempt(current.current);setStatus(changed?'Saving…':'Saved');setError(undefined);if(changed)persist();else localStorage.removeItem(storageKey);return current.current;
  }).catch((e:unknown)=>{if(e instanceof ApiError&&[401,403,409,422].includes(e.status)){blocked.current=true;setConflict(true);setStatus('Needs attention');setError(e);}else{setStatus('Offline · saved on this device');setError(new Error('Could not reach the server. Your draft is kept on this device and will retry.'));}throw e;}).finally(()=>{inFlight.current=null;});inFlight.current=promise;return promise;
 },[persist,storageKey]);
 useEffect(()=>{const id=window.setInterval(()=>{if(dirty.current&&!blocked.current)void flush().catch(()=>{});},1800);const online=()=>void flush().catch(()=>{});window.addEventListener('online',online);const before=(e:BeforeUnloadEvent)=>{if(dirty.current){persist();e.preventDefault();}};window.addEventListener('beforeunload',before);return()=>{clearInterval(id);window.removeEventListener('online',online);window.removeEventListener('beforeunload',before);};},[flush,persist]);
 function change(partial:Partial<Attempt>){current.current={...current.current,...partial};generation.current++;dirty.current=true;setAttempt(current.current);setStatus(navigator.onLine?'Saving…':'Offline · saved on this device');persist();}
 async function action(action:'submit'|'pause'|'resume'){
  await flush();if(dirty.current)await flush();if(blocked.current||dirty.current)throw new Error('Wait for your latest work to finish saving before continuing. Resolve any saved-work conflict first.');
  const result=await api<Attempt>(`/attempts/${initial.id}/${action}`,'POST',{revision:current.current.revision,request_key:key()});current.current=result;setAttempt(result);localStorage.removeItem(storageKey);dirty.current=false;return result;
 }
 async function reload(){const saved=await api<Attempt>(`/attempts/${initial.id}`);current.current=saved;setAttempt(saved);dirty.current=false;pending.current=null;blocked.current=false;setConflict(false);setError(undefined);setStatus('Saved');localStorage.removeItem(storageKey);return saved;}
 function acceptUpload(a:Attempt){current.current={...current.current,revision:a.revision,recordings:a.recordings,answers:{...current.current.answers,...a.answers}};setAttempt(current.current);}
 return {attempt,status,error,conflict,change,flush,action,reload,acceptUpload,storageKey,hasUnsaved:()=>dirty.current};
}
