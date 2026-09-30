import { useEffect,useRef,type ReactNode } from 'react';
import { Link } from 'react-router-dom';
import { ArrowRight,BookOpen,Headphones,PenLine,Mic,Clock3,FileText,LockKeyhole,AlertCircle,Check,X } from 'lucide-react';
import type { Test,Skill } from '../../../shared/api';
import { duration,human,isDone } from '../lib/api';
export const skills:Skill[]=['listening','reading','writing','speaking'];
export const SkillIcon=({skill,size=22}:{skill:Skill;size?:number})=>{const Icon={reading:BookOpen,listening:Headphones,writing:PenLine,speaking:Mic}[skill];return <Icon size={size} strokeWidth={1.7}/>;};
export function Badge({children,tone='neutral'}:{children:ReactNode;tone?:string}){return <span className={`badge ${tone}`}>{children}</span>;}
export function Loading(){return <div className="empty" role="status"><span className="spinner"/>Loading your next step…</div>;}
export function ErrorBox({error,retry}:{error:unknown;retry?:()=>void}){return <div className="notice error" role="alert"><AlertCircle size={20}/><div>{error instanceof Error?error.message:String(error)}{retry&&<button className="text-button" onClick={retry}>Try again</button>}</div></div>;}
export function Empty({title,children}:{title:string;children:ReactNode}){return <div className="empty"><BookOpen size={30}/><h3>{title}</h3><p>{children}</p></div>;}
export function PageTitle({eyebrow,title,description,action}:{eyebrow?:string;title:string;description?:string;action?:ReactNode}){return <div className="page-title"><div>{eyebrow&&<p className="eyebrow">{eyebrow}</p>}<h1>{title}</h1>{description&&<p className="muted">{description}</p>}</div>{action}</div>;}
export function TestCard({test}:{test:Test}){
 const status=test.attempt?.status;const href=status?`/${isDone(status)?'results':'attempts'}/${test.attempt!.id}`:`/library/${test.id}`;
 return <article className="test-card"><div className={`test-cover ${test.skill}`}><span className="cover-circle one"/><span className="cover-circle two"/><div className="cover-icon"><SkillIcon skill={test.skill} size={34}/></div><div className="cover-label">{human(test.skill)}<span>{test.duration_seconds>=1800?'THE FULL PICTURE':'ONE FOCUSED STEP'}</span></div><Badge tone="paper">{test.premium?<><LockKeyhole size={12}/> Premium</>:'Free practice'}</Badge></div><div className="test-card-body"><div className="row between"><span className="eyebrow small">{test.test_type==='academic'?'ACADEMIC':'GENERAL TRAINING'}</span>{status&&<Badge tone={isDone(status)?'green':'indigo'}>{isDone(status)?'Completed':'In progress'}</Badge>}</div><h3><Link to={`/library/${test.id}`}>{test.title}</Link></h3><p className="card-description">{test.collection??(test.tags?.join(' · ')||'Build your skills, one exercise at a time.')}</p><div className="card-meta"><span><Clock3 size={15}/>{duration(test.duration_seconds)}</span><span><FileText size={15}/>{test.question_count} {['writing','speaking'].includes(test.skill)?'prompts':'questions'}</span></div><div className="card-bottom"><span className="muted text-xs">Demonstration material</span><Link className="text-button" to={href}>{status?isDone(status)?'Review':'Resume':'Explore test'}<ArrowRight size={16}/></Link></div></div></article>;
}
export function Modal({title,children,onClose}:{title:string;children:ReactNode;onClose:()=>void}){
 const ref=useRef<HTMLDialogElement>(null);useEffect(()=>{const d=ref.current;d?.showModal();return()=>d?.close();},[]);
 return <dialog ref={ref} className="dialog" onCancel={onClose}><div className="row between"><h2>{title}</h2><button className="icon-button" aria-label="Close dialog" onClick={onClose}><X/></button></div>{children}</dialog>;
}
export function Success({children}:{children:ReactNode}){return <div className="notice success" role="status"><Check size={20}/>{children}</div>;}
