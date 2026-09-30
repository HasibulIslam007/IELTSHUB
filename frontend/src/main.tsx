import { StrictMode,type ReactNode } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter,Routes,Route,Navigate,Link } from 'react-router-dom';
import { QueryClientProvider } from '@tanstack/react-query';
import { queryClient } from './lib/api';
import { SessionProvider,useSession } from './lib/session';
import { Layout } from './components/Layout';
import { Loading } from './components/UI';
import { Home } from './pages/Home';
import { AuthPage } from './pages/Auth';
import { Library,TestDetail } from './pages/Library';
import { Dashboard } from './pages/Dashboard';
import { Exam,Sample } from './pages/Exam';
import { Results } from './pages/Results';
import { Progress,StudyPlan,Mistakes } from './pages/Progress';
import { Settings,Notifications } from './pages/Settings';
import { PublicPage } from './pages/PublicPages';
import './styles.css';
import { Seo } from './components/Seo';
function Protected({children}:{children:ReactNode}){const {user,loading}=useSession();if(loading)return <Loading/>;return user?children:<Navigate to={`/login?next=${encodeURIComponent(location.pathname)}`} replace/>;}
function App(){return <Routes><Route element={<Layout/>}><Route index element={<Home/>}/><Route path="library" element={<Library/>}/><Route path="library/:id" element={<TestDetail/>}/><Route path="sample" element={<Sample/>}/><Route path="login" element={<AuthPage mode="login"/>}/><Route path="register" element={<AuthPage mode="register"/>}/><Route path="forgot-password" element={<AuthPage mode="forgot"/>}/><Route path="reset-password/:token" element={<AuthPage mode="reset"/>}/>{['help','faq','how-it-works','pricing','contact','privacy','terms','verify-email'].map(page=><Route key={page} path={page} element={<PublicPage page={page}/>}/>)}<Route path="dashboard" element={<Protected><Dashboard/></Protected>}/><Route path="progress" element={<Protected><Progress/></Protected>}/><Route path="study-plan" element={<Protected><StudyPlan/></Protected>}/><Route path="mistakes" element={<Protected><Mistakes/></Protected>}/><Route path="settings" element={<Protected><Settings/></Protected>}/><Route path="onboarding" element={<Protected><Settings onboarding/></Protected>}/><Route path="notifications" element={<Protected><Notifications/></Protected>}/><Route path="results/:id" element={<Protected><Results/></Protected>}/><Route path="*" element={<div className="empty"><h1>This page has moved out of reach.</h1><Link className="button primary" to="/library">Return to the library</Link></div>}/></Route><Route path="attempts/:id" element={<Protected><Exam/></Protected>}/></Routes>;}
createRoot(document.getElementById('root')!).render(<StrictMode><QueryClientProvider client={queryClient}><BrowserRouter><SessionProvider><Seo/><App/></SessionProvider></BrowserRouter></QueryClientProvider></StrictMode>);
