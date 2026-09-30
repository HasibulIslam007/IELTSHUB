import { clearRecordings } from './recordingStore';
import { QueryClient } from '@tanstack/react-query';
export class ApiError extends Error { constructor(public status:number,message:string,public errors?:Record<string,string[]>){super(message);} }
export const queryClient=new QueryClient({defaultOptions:{queries:{retry:(n,e)=>!(e instanceof ApiError&&e.status<500)&&n<1,staleTime:30000,refetchOnWindowFocus:false}}});
export function csrfToken(){return decodeURIComponent(document.cookie.split('; ').find(x=>x.startsWith('XSRF-TOKEN='))?.split('=').slice(1).join('=')??'');}
export async function csrf(){await fetch('/sanctum/csrf-cookie',{credentials:'same-origin',headers:{Accept:'application/json'}});}
export async function api<T>(path:string,method='GET',body?:unknown):Promise<T>{
 if(method!=='GET'&&!csrfToken())await csrf();
 const response=await fetch('/api/v1'+path,{method,credentials:'same-origin',headers:{Accept:'application/json',...(body instanceof FormData?{}:{'Content-Type':'application/json'}),'X-XSRF-TOKEN':csrfToken(),'X-Requested-With':'XMLHttpRequest'},body:body===undefined?undefined:body instanceof FormData?body:JSON.stringify(body)});
 if(response.status===204)return undefined as T;
 const data=await response.json().catch(()=>({message:'The server could not return a response. Please retry.'}));
 if(!response.ok)throw new ApiError(response.status,data.message??'Request failed.',data.errors);return data;
}
export const key=()=>crypto.randomUUID();
export const human=(s:string)=>s.replaceAll('_',' ').replace(/^./,c=>c.toUpperCase());
export const duration=(s:number)=>`${Math.round(s/60)} min`;
export const isDone=(s:string)=>['submitted','awaiting_review','reviewed'].includes(s);
export function clearDrafts(){void clearRecordings().catch(()=>{});Object.keys(localStorage).filter(k=>k.startsWith('hub-draft:')||k.startsWith('hub-audio:')).forEach(k=>localStorage.removeItem(k));}
