import { createContext,useContext,type ReactNode } from 'react';
import { useQuery } from '@tanstack/react-query';
import { api,ApiError } from './api';
import type { User,HubConfig } from '../../../shared/api';
const Session=createContext<{user:User|null;loading:boolean;refresh:()=>Promise<unknown>;config:HubConfig|undefined}>({user:null,loading:true,refresh:async()=>{},config:undefined});
export function SessionProvider({children}:{children:ReactNode}){
 const profile=useQuery({queryKey:['profile'],queryFn:async()=>{try{return await api<{user:User}>('/profile');}catch(e){if(e instanceof ApiError&&e.status===401)return {user:null};throw e;}},retry:false});
 const config=useQuery({queryKey:['config'],queryFn:()=>api<HubConfig>('/config')});
 return <Session.Provider value={{user:profile.data?.user??null,loading:profile.isPending,refresh:profile.refetch,config:config.data}}>{children}</Session.Provider>;
}
export const useSession=()=>useContext(Session);
