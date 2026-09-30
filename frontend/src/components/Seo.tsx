import { useEffect } from 'react';
import { useLocation } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import pages from '../../../shared/seo.json';
import type { Test } from '../../../shared/api';
import { api } from '../lib/api';
import { useSession } from '../lib/session';

type Metadata = { title: string; description: string; public: boolean };

export function Seo() {
  const { pathname } = useLocation();
  const { user } = useSession();
  const id = pathname.match(/^\/library\/(\d+)$/)?.[1];
  const test = useQuery({
    queryKey: ['test', id, user?.id],
    queryFn: () => api<Test>(`/tests/${id}`),
    enabled: Boolean(id),
  });

  useEffect(() => {
    let page: Metadata = (pages as Record<string, Metadata>)[pathname] ?? {
      title: 'Page not found', description: 'Return to the IELTS practice library.', public: false,
    };
    if (id && test.data) {
      page = { title: test.data.title, description: test.data.description, public: true };
    } else if (pathname.startsWith('/attempts/')) {
      page = { title: 'Your Practice Session', description: 'Your saved IELTS practice session.', public: false };
    } else if (pathname.startsWith('/results/')) {
      page = { title: 'Your Practice Results', description: 'Review your answers and teacher feedback.', public: false };
    } else if (pathname.startsWith('/reset-password/')) {
      page = { title: 'Choose a New Password', description: 'Recover access to your learning account.', public: false };
    }
    const title = pathname === '/' ? page.title : `${page.title} | IELTS Practice Hub`;
    const canonical = document.querySelector<HTMLLinkElement>('link[rel=canonical]');
    const url = new URL(pathname, canonical?.href ?? window.location.origin).href;
    document.title = title;
    if (canonical) canonical.href = url;
    for (const [selector, value] of [
      ['meta[name=description]', page.description],
      ['meta[name=robots]', page.public ? 'index, follow' : 'noindex, nofollow'],
      ['meta[property="og:title"]', title],
      ['meta[property="og:description"]', page.description],
      ['meta[property="og:url"]', url],
      ['meta[name="twitter:title"]', title],
      ['meta[name="twitter:description"]', page.description],
    ]) document.querySelector(selector)?.setAttribute('content', value);
  }, [pathname, id, test.data]);

  return null;
}
