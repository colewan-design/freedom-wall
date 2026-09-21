<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const page = usePage();
const authUser = computed(() => page.props.auth?.user ?? null);
const pendingRequestCount = computed(() => page.props.pendingRequestCount ?? 0);
const unreadMessageCount = computed(() => page.props.unreadMessageCount ?? 0);
const storyFriends = computed(() => page.props.storyFriends ?? []);
const suggestedFriends = computed(() => page.props.suggestedFriends ?? []);
const requestedIds = ref([]);
const search = ref('');

const firstName = computed(() => authUser.value?.name?.split(' ')[0] || 'Student');
const initial = computed(() => authUser.value?.name?.slice(0, 1).toUpperCase() || 'S');
const isActive = (prefix) => page.url.startsWith(prefix);

const navItems = [
  { label: 'Home', href: '/feed', icon: 'home' },
  { label: 'Network', href: '/friends', icon: 'network', count: pendingRequestCount },
  { label: 'Messages', href: '/messages', icon: 'mail', count: unreadMessageCount },
  { label: 'Communities', href: null, icon: 'community' },
  { label: 'Forums', href: '/wall', icon: 'forum' },
  { label: 'Events', href: null, icon: 'calendar' },
  { label: 'Opportunities', href: null, icon: 'briefcase' },
  { label: 'Marketplace', href: null, icon: 'store' },
  { label: 'Journal', href: '/journal', icon: 'journal' },
  { label: 'Saved', href: '/saved', icon: 'bookmark' },
];

const wallItems = [
  { label: 'Anonymous Feed', href: '/wall', icon: 'forum' },
  { label: 'Threads', href: '/threads', icon: 'hash' },
  { label: 'Global Chat', href: '/chat', icon: 'globe' },
  { label: 'ID Check', href: '/id-check', icon: 'shield' },
];

const trending = [
  ['#Midterms', '428 posts'],
  ['#PartTimeJobs', '312 posts'],
  ['#BSUIntrams', '298 posts'],
  ['#StudyGroup', '256 posts'],
  ['#BSUCommunity', '201 posts'],
];

const events = [
  { month: 'SEP', day: '24', title: 'BSU Community Assembly', place: 'University Gymnasium', time: 'Tue, Sep 24 · 8:00 AM' },
  { month: 'SEP', day: '27', title: 'Career Talk: Tech in the North', place: 'Audio Visual Room', time: 'Fri, Sep 27 · 1:00 PM' },
  { month: 'OCT', day: '02', title: 'Mental Health Awareness Week', place: 'Student Center', time: 'Wed, Oct 2 · 9:00 AM' },
];

function followSuggestion(user) {
  if (requestedIds.value.includes(user.id)) return;
  requestedIds.value.push(user.id);
  router.post(route('friends.store', user.username), {}, { preserveScroll: true });
}

function logout() {
  router.post(route('logout'));
}

function submitSearch() {
  const query = search.value.trim();
  if (!query) return;
  router.get(route('friends.index'), { q: query });
}
</script>

<template>
  <div class="feed-shell">
    <div class="feed-frame">
      <aside class="feed-sidebar">
        <Link href="/feed" class="brand-lockup" aria-label="BSU Connect home">
          <img src="/images/branding/bsufw-mark-64.png" alt="" />
          <span>
            <strong>BSU Connect</strong>
            <small>Students. Ideas. Opportunities.</small>
          </span>
        </Link>

        <div class="sidebar-profile">
          <Link :href="`/profile/${authUser?.username}`" class="avatar avatar-lg">
            <img v-if="authUser?.avatar_url" :src="authUser.avatar_url" alt="" />
            <span v-else>{{ initial }}</span>
            <i aria-hidden="true"></i>
          </Link>
          <div class="sidebar-profile-copy">
            <strong>{{ authUser?.name }}</strong>
            <span>@{{ authUser?.username }}</span>
            <small>BSU · Student</small>
          </div>
          <Link href="/settings/profile" class="edit-profile" aria-label="Edit profile">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m4 16-.7 4.7L8 20l11-11-4-4L4 16Z"/><path d="m13.8 6.2 4 4"/></svg>
          </Link>
        </div>

        <nav class="primary-nav" aria-label="Student navigation">
          <component
            :is="item.href ? Link : 'span'"
            v-for="item in navItems"
            :key="item.label"
            :href="item.href || undefined"
            class="nav-row"
            :class="{ active: item.href && isActive(item.href), inert: !item.href }"
          >
            <svg v-if="item.icon === 'home'" viewBox="0 0 24 24"><path d="m3 11 9-8 9 8"/><path d="M5 10v10h14V10M9 20v-6h6v6"/></svg>
            <svg v-else-if="item.icon === 'network'" viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"/><path d="M3 20c0-3 2.5-5 6-5s6 2 6 5"/><circle cx="17" cy="9" r="2.5"/><path d="M15.5 15.5c3.5-.4 5.5 1.2 5.5 4.5"/></svg>
            <svg v-else-if="item.icon === 'mail'" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg>
            <svg v-else-if="item.icon === 'community'" viewBox="0 0 24 24"><circle cx="12" cy="6" r="2.5"/><circle cx="5.5" cy="10" r="2"/><circle cx="18.5" cy="10" r="2"/><path d="M7 20c0-3 2-5 5-5s5 2 5 5M2 19c0-2.5 1.4-4 3.5-4M22 19c0-2.5-1.4-4-3.5-4"/></svg>
            <svg v-else-if="item.icon === 'forum'" viewBox="0 0 24 24"><path d="M4 5h16v11H9l-5 4v-4H4V5Z"/><circle cx="9" cy="10.5" r=".7"/><circle cx="12" cy="10.5" r=".7"/><circle cx="15" cy="10.5" r=".7"/></svg>
            <svg v-else-if="item.icon === 'calendar'" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18"/></svg>
            <svg v-else-if="item.icon === 'briefcase'" viewBox="0 0 24 24"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M9 7V4h6v3M3 12h18"/></svg>
            <svg v-else-if="item.icon === 'store'" viewBox="0 0 24 24"><path d="m4 9 1-5h14l1 5"/><path d="M5 12v8h14v-8M4 9c0 2 3 3 4 0 0 2 3 3 4 0 0 2 3 3 4 0 1 3 4 2 4 0"/></svg>
            <svg v-else-if="item.icon === 'journal'" viewBox="0 0 24 24"><path d="M5 3h13a2 2 0 0 1 2 2v16H7a2 2 0 0 1-2-2V3Z"/><path d="M5 17a2 2 0 0 1 2-2h13M9 7h7M9 10h5"/></svg>
            <svg v-else viewBox="0 0 24 24"><path d="M6 3h12v18l-6-4-6 4V3Z"/></svg>
            <span>{{ item.label }}</span>
            <b v-if="item.count?.value > 0">{{ item.count.value }}</b>
          </component>
        </nav>

        <div class="wall-nav">
          <div class="wall-nav-title">
            <span><svg viewBox="0 0 24 24"><path d="M4 5h16v12l-8 4-8-4V5Z"/><path d="M8 11c1.4 1.5 2.6 1.5 4 0 1.4 1.5 2.6 1.5 4 0"/></svg>Freedom Wall</span>
            <svg viewBox="0 0 24 24" class="chevron"><path d="m8 14 4-4 4 4"/></svg>
          </div>
          <Link v-for="item in wallItems" :key="item.label" :href="item.href" class="nav-row nav-sub" :class="{ active: isActive(item.href) && !isActive('/feed') }">
            <svg v-if="item.icon === 'forum'" viewBox="0 0 24 24"><path d="M4 5h16v11H9l-5 4v-4H4V5Z"/><circle cx="9" cy="10.5" r=".7"/><circle cx="12" cy="10.5" r=".7"/><circle cx="15" cy="10.5" r=".7"/></svg>
            <svg v-else-if="item.icon === 'hash'" viewBox="0 0 24 24"><path d="M9 3 7 21M17 3l-2 18M4 9h17M3 15h17"/></svg>
            <svg v-else-if="item.icon === 'globe'" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18"/></svg>
            <svg v-else viewBox="0 0 24 24"><path d="M12 3 4 6v6c0 5 3.4 8 8 10 4.6-2 8-5 8-10V6l-8-3Z"/></svg>
            <span>{{ item.label }}</span>
          </Link>
        </div>

        <div class="sidebar-bottom">
          <Link href="/settings/profile" class="nav-row"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19 12a7 7 0 0 0-.1-1.2l2-1.5-2-3.5-2.3 1a8 8 0 0 0-2-1.2L14.3 3H9.7l-.3 2.6a8 8 0 0 0-2 1.2l-2.3-1-2 3.5 2 1.5a7 7 0 0 0 0 2.4l-2 1.5 2 3.5 2.3-1a8 8 0 0 0 2 1.2l.3 2.6h4.6l.3-2.6a8 8 0 0 0 2-1.2l2.3 1 2-3.5-2-1.5A7 7 0 0 0 19 12Z"/></svg>Settings</Link>
          <button type="button" class="logout-link" @click="logout">Log out</button>
        </div>
      </aside>

      <div class="feed-workspace">
        <header class="topbar">
          <form class="global-search" @submit.prevent="submitSearch">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
            <input v-model="search" type="search" placeholder="Search for students, posts, events, communities..." aria-label="Search BSU Connect" />
          </form>
          <div class="topbar-actions">
            <button type="button" class="icon-button" aria-label="Notifications"><svg viewBox="0 0 24 24"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9ZM10 21h4"/></svg><i></i></button>
            <Link href="/messages" class="icon-button" aria-label="Messages"><svg viewBox="0 0 24 24"><path d="M4 5h16v12H8l-4 3V5Z"/><circle cx="9" cy="11" r=".7"/><circle cx="12" cy="11" r=".7"/><circle cx="15" cy="11" r=".7"/></svg></Link>
            <Link :href="`/profile/${authUser?.username}`" class="top-avatar">{{ initial }}</Link>
            <button type="button" class="profile-caret" aria-label="Account menu" @click="logout"><svg viewBox="0 0 24 24"><path d="m8 10 4 4 4-4"/></svg></button>
          </div>
        </header>

        <main class="feed-main"><slot /></main>

        <aside class="feed-right">
          <section class="rail-panel stories-panel">
            <div class="rail-heading"><h2><span>♣</span> Stories</h2><Link href="/friends">See all</Link></div>
            <div class="story-row">
              <button type="button" class="story-card create-story"><span class="story-add">+</span><strong>Create<br />Story</strong></button>
              <Link v-for="friend in storyFriends.slice(0, 4)" :key="friend.id" :href="`/profile/${friend.username}`" class="story-card" :style="friend.avatar_url ? { backgroundImage: `url(${friend.avatar_url})` } : null">
                <span class="story-ring"><img v-if="friend.avatar_url" :src="friend.avatar_url" alt="" /><span v-else>{{ friend.name.slice(0, 1) }}</span></span>
                <strong>{{ friend.name.split(' ')[0] }}</strong>
              </Link>
              <div v-if="!storyFriends.length" class="story-card story-placeholder"><span class="story-ring">B</span><strong>BSU SSC</strong></div>
            </div>
          </section>

          <section class="rail-panel suggestions-panel">
            <div class="rail-heading"><h2><span>♣</span> People you may know</h2><Link href="/friends">See all</Link></div>
            <ul class="suggestion-list">
              <li v-for="person in suggestedFriends" :key="person.id">
                <span class="avatar avatar-sm"><img v-if="person.avatar_url" :src="person.avatar_url" alt="" /><span v-else>{{ person.name.slice(0, 1) }}</span></span>
                <span class="person-copy"><strong>{{ person.name }}</strong><small>@{{ person.username }}</small><em>BSU student</em></span>
                <button type="button" :disabled="requestedIds.includes(person.id)" @click="followSuggestion(person)">{{ requestedIds.includes(person.id) ? 'Requested' : 'Connect' }}</button>
              </li>
              <li v-if="!suggestedFriends.length" class="suggestion-empty">Your network is all caught up.</li>
            </ul>
          </section>

          <section class="rail-panel trending-panel">
            <div class="rail-heading"><h2><span>🔥</span> Trending at BSU</h2><Link href="/wall">See all</Link></div>
            <ul><li v-for="item in trending" :key="item[0]"><b>#</b><strong>{{ item[0] }}</strong><span>{{ item[1] }}</span></li></ul>
          </section>

          <section class="rail-panel events-panel">
            <div class="rail-heading"><h2><span>▣</span> Upcoming Events</h2><a href="#events">See all</a></div>
            <ul>
              <li v-for="event in events" :key="event.title">
                <time><b>{{ event.month }}</b><strong>{{ event.day }}</strong></time>
                <span class="event-copy"><strong>{{ event.title }}</strong><small>⌖ {{ event.place }}</small><small>▧ {{ event.time }}</small></span>
                <button type="button">Interested</button>
              </li>
            </ul>
          </section>
        </aside>
      </div>
    </div>
  </div>
</template>

<style scoped>
.feed-shell {
  --feed-green: #075b32;
  --feed-green-dark: #043f24;
  --feed-green-soft: #eaf5ef;
  --feed-ink: #17211b;
  --feed-muted: #69766f;
  --feed-line: #dce5e0;
  --feed-bg: #f6f9f7;
  --nf-bg: #f6f9f7;
  --nf-panel: #ffffff;
  --nf-line: #dce5e0;
  --nf-ink: #17211b;
  --nf-muted: #69766f;
  --nf-accent: #075b32;
  --nf-accent-contrast: #ffffff;
  --nf-surface-2: #f2f6f4;
  min-height: 100vh;
  color: var(--feed-ink);
  background: var(--feed-bg);
  font-family: Inter, var(--b-sans);
}

.feed-frame {
  max-width: 1728px;
  margin: 0 auto;
  display: flex;
  align-items: flex-start;
}

.feed-sidebar {
  position: sticky;
  top: 0;
  z-index: 20;
  width: 292px;
  flex: 0 0 292px;
  height: 100vh;
  padding: 14px 18px 18px 32px;
  display: flex;
  flex-direction: column;
  background: #fff;
  border-right: 1px solid var(--feed-line);
  overflow-y: auto;
}

.brand-lockup { display: flex; align-items: center; gap: 12px; color: var(--feed-green-dark); text-decoration: none; padding: 0 10px 16px 8px; border-bottom: 1px solid var(--feed-line); }
.brand-lockup img { width: 49px; height: 49px; object-fit: contain; }
.brand-lockup span { display: flex; flex-direction: column; line-height: 1.15; }
.brand-lockup strong { font-size: 20px; letter-spacing: -.4px; }
.brand-lockup small { margin-top: 5px; color: #7b8781; font-size: 10px; }

.sidebar-profile { display: flex; align-items: center; gap: 11px; padding: 22px 8px 18px; border-bottom: 1px solid var(--feed-line); }
.avatar { position: relative; display: grid; place-items: center; flex: 0 0 auto; overflow: visible; border-radius: 50%; background: #e7f0eb; color: var(--feed-green); font-weight: 800; }
.avatar img { width: 100%; height: 100%; border-radius: inherit; object-fit: cover; }
.avatar-lg { width: 52px; height: 52px; font-size: 21px; }
.avatar-lg i { position: absolute; right: 0; bottom: 1px; width: 12px; height: 12px; border-radius: 50%; background: #10a75a; border: 2px solid #fff; }
.avatar-sm { width: 42px; height: 42px; font-size: 16px; }
.sidebar-profile-copy { display: flex; flex-direction: column; min-width: 0; flex: 1; }
.sidebar-profile-copy strong { overflow: hidden; font-size: 14px; text-overflow: ellipsis; white-space: nowrap; }
.sidebar-profile-copy span, .sidebar-profile-copy small { color: var(--feed-muted); font-size: 11px; }
.sidebar-profile-copy small { margin-top: 3px; }
.edit-profile { width: 26px; height: 26px; color: #5d6b64; }
.edit-profile svg { width: 18px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }

.primary-nav { display: flex; flex-direction: column; gap: 3px; padding: 12px 0; }
.nav-row { min-height: 41px; padding: 0 13px; display: flex; align-items: center; gap: 14px; border-radius: 7px; color: #435048; font-size: 14px; font-weight: 500; text-decoration: none; transition: .15s ease; }
.nav-row svg { width: 19px; height: 19px; flex: 0 0 auto; fill: none; stroke: currentColor; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }
.nav-row:hover:not(.inert) { background: #f1f6f3; color: var(--feed-green); }
.nav-row.active { color: #fff; background: linear-gradient(135deg, #08713e, #07562f); box-shadow: 0 5px 12px rgba(4, 91, 49, .16); }
.nav-row.inert { cursor: default; }
.nav-row b { display: grid; place-items: center; margin-left: auto; min-width: 22px; height: 22px; padding: 0 5px; border-radius: 50px; background: var(--feed-green); color: #fff; font-size: 11px; }
.nav-row.active b { color: var(--feed-green); background: #fff; }
.wall-nav { padding: 13px 0; border-top: 1px solid var(--feed-line); border-bottom: 1px solid var(--feed-line); }
.wall-nav-title { display: flex; align-items: center; justify-content: space-between; margin: 0 12px 7px; color: var(--feed-green); font-size: 14px; font-weight: 800; }
.wall-nav-title span { display: flex; align-items: center; gap: 13px; }
.wall-nav-title svg { width: 19px; fill: none; stroke: currentColor; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }
.wall-nav-title .chevron { width: 15px; }
.nav-sub { min-height: 35px; padding-left: 28px; color: #66736c; font-size: 13px; }
.nav-sub.active { background: #eaf5ef; color: var(--feed-green); box-shadow: none; }
.sidebar-bottom { margin-top: auto; padding-top: 14px; }
.logout-link { margin-left: 46px; border: 0; background: none; color: #87918c; font: inherit; font-size: 12px; cursor: pointer; }

.feed-workspace { min-height: 100vh; flex: 1; min-width: 0; display: grid; grid-template-columns: minmax(0, 1fr) 420px; grid-template-rows: 58px auto; }
.topbar { grid-column: 1 / -1; position: sticky; top: 0; z-index: 15; height: 58px; display: flex; align-items: center; gap: 28px; padding: 8px 24px 8px 16px; background: rgba(255,255,255,.96); border-bottom: 1px solid #edf1ef; backdrop-filter: blur(12px); }
.global-search { display: flex; align-items: center; flex: 1; max-width: 780px; height: 40px; padding: 0 13px; border: 1px solid #d9e1dd; border-radius: 8px; background: #f6f8f7; }
.global-search svg { width: 19px; fill: none; stroke: #66726c; stroke-width: 1.8; stroke-linecap: round; }
.global-search input { width: 100%; height: 100%; padding: 0 11px; border: 0; outline: 0; background: transparent; color: var(--feed-ink); font: inherit; font-size: 12px; }
.global-search input::placeholder { color: #87928c; }
.topbar-actions { margin-left: auto; display: flex; align-items: center; gap: 15px; }
.icon-button, .profile-caret { position: relative; display: grid; place-items: center; width: 29px; height: 29px; border: 0; background: none; color: #26382f; cursor: pointer; text-decoration: none; }
.icon-button svg, .profile-caret svg { width: 20px; fill: none; stroke: currentColor; stroke-width: 1.6; stroke-linecap: round; stroke-linejoin: round; }
.icon-button i { position: absolute; top: 1px; right: 0; width: 8px; height: 8px; border: 2px solid #fff; border-radius: 50%; background: #058145; }
.top-avatar { display: grid; place-items: center; width: 36px; height: 36px; border-radius: 50%; background: #e9f2ed; color: var(--feed-green); font-size: 14px; font-weight: 800; text-decoration: none; }
.profile-caret { margin-left: -10px; }

.feed-main { min-width: 0; padding: 0 16px 40px; }
.feed-right { padding: 0 16px 40px 0; display: flex; flex-direction: column; gap: 10px; }
.rail-panel { border: 1px solid var(--feed-line); border-radius: 11px; background: #fff; padding: 13px 15px; box-shadow: 0 2px 8px rgba(17,53,33,.035); }
.rail-heading { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
.rail-heading h2 { display: flex; align-items: center; gap: 7px; margin: 0; color: #1b2921; font-size: 14px; }
.rail-heading h2 span { color: var(--feed-green); }
.rail-heading a { color: var(--feed-green); font-size: 10px; font-weight: 700; text-decoration: none; }
.story-row { display: flex; gap: 8px; overflow-x: auto; scrollbar-width: none; }
.story-card { position: relative; width: 72px; height: 104px; flex: 0 0 72px; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; padding: 7px 4px; overflow: hidden; border: 1px solid #dce4e0; border-radius: 9px; background: #f4f7f5 center/cover; color: #fff; text-decoration: none; }
.story-card::after { content: ''; position: absolute; inset: 36% 0 0; background: linear-gradient(transparent, rgba(2,19,10,.76)); }
.story-card strong { position: relative; z-index: 1; max-width: 100%; overflow: hidden; font-size: 9px; text-overflow: ellipsis; white-space: nowrap; text-shadow: 0 1px 3px #000; }
.story-ring { position: absolute; z-index: 1; top: 9px; display: grid; place-items: center; width: 38px; height: 38px; overflow: hidden; border: 2px solid #fff; outline: 2px solid #635ce2; border-radius: 50%; background: #dfeae4; color: var(--feed-green); font-size: 14px; font-weight: 800; }
.story-ring img { width: 100%; height: 100%; object-fit: cover; }
.create-story { justify-content: flex-end; border: 0; background: #f4f7f5; color: #17211b; cursor: pointer; }
.create-story::after { display: none; }
.create-story strong { color: #17211b; white-space: normal; text-shadow: none; line-height: 1.1; }
.story-add { display: grid; place-items: center; width: 31px; height: 31px; margin-bottom: 14px; border-radius: 50%; background: var(--feed-green); color: #fff; font-size: 23px; line-height: 1; }
.story-placeholder::after { display: none; }
.story-placeholder { color: var(--feed-green); }
.story-placeholder strong { color: var(--feed-green); text-shadow: none; }

.suggestion-list, .trending-panel ul, .events-panel ul { list-style: none; margin: 0; padding: 0; }
.suggestion-list li { display: flex; align-items: center; gap: 9px; min-height: 53px; }
.person-copy { display: flex; min-width: 0; flex: 1; flex-direction: column; }
.person-copy strong { overflow: hidden; color: #17211b; font-size: 12px; text-overflow: ellipsis; white-space: nowrap; }
.person-copy small, .person-copy em { color: #7b8781; font-size: 9px; font-style: normal; }
.suggestion-list button { min-width: 78px; padding: 8px 10px; border: 0; border-radius: 5px; background: var(--feed-green); color: #fff; font-size: 10px; font-weight: 700; cursor: pointer; }
.suggestion-list button:disabled { background: #dce9e2; color: var(--feed-green); }
.suggestion-list .suggestion-empty { color: var(--feed-muted); font-size: 12px; }
.trending-panel ul { display: flex; flex-direction: column; gap: 8px; }
.trending-panel li { display: flex; align-items: center; gap: 10px; font-size: 11px; }
.trending-panel li b { color: #7a8680; }
.trending-panel li strong { flex: 1; color: var(--feed-green); }
.trending-panel li span { color: #88948e; font-size: 9px; }
.events-panel li { display: flex; align-items: center; gap: 10px; padding: 7px 0; }
.events-panel time { width: 43px; height: 49px; display: flex; flex-direction: column; align-items: center; justify-content: center; border-radius: 6px; background: #f8f4f4; }
.events-panel time b { color: #d63d3d; font-size: 9px; }
.events-panel time strong { font-size: 17px; }
.event-copy { display: flex; min-width: 0; flex: 1; flex-direction: column; }
.event-copy strong { overflow: hidden; font-size: 11px; text-overflow: ellipsis; white-space: nowrap; }
.event-copy small { color: #7a8780; font-size: 8px; }
.events-panel li button { padding: 8px 10px; border: 1px solid var(--feed-green); border-radius: 5px; background: #fff; color: var(--feed-green); font-size: 9px; font-weight: 700; }

@media (max-width: 1260px) {
  .feed-sidebar { width: 238px; flex-basis: 238px; padding-left: 17px; }
  .feed-workspace { grid-template-columns: minmax(0, 1fr) 340px; }
  .brand-lockup strong { font-size: 18px; }
  .feed-right { padding-right: 10px; }
}

@media (max-width: 1020px) {
  .feed-workspace { grid-template-columns: minmax(0, 1fr); }
  .feed-right { display: none; }
}

@media (max-width: 760px) {
  .feed-frame { display: block; }
  .feed-sidebar { width: 100%; height: auto; min-height: 62px; padding: 8px 12px; flex-direction: row; align-items: center; overflow-x: auto; border-right: 0; border-bottom: 1px solid var(--feed-line); }
  .brand-lockup { flex: 0 0 auto; padding: 0 14px 0 0; border: 0; }
  .brand-lockup img { width: 39px; height: 39px; }
  .brand-lockup small, .sidebar-profile, .wall-nav, .sidebar-bottom { display: none; }
  .primary-nav { flex-direction: row; padding: 0; }
  .nav-row { min-width: 43px; min-height: 43px; justify-content: center; padding: 0 12px; }
  .nav-row span { display: none; }
  .nav-row b { position: absolute; transform: translate(12px,-12px); }
  .feed-workspace { display: block; }
  .topbar { position: static; height: 56px; padding: 8px 12px; }
  .topbar-actions .icon-button, .profile-caret { display: none; }
  .global-search { max-width: none; }
  .feed-main { padding: 0 10px 28px; }
}
</style>
