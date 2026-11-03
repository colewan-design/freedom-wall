/**
 * Everything we've shipped, in one place.
 *
 * The /tools page renders this whole list; the "Other Tools" panel in the
 * newsfeed sidebar shows a short slice of the off-site ones. Adding an app here
 * is enough for both — nothing else needs touching.
 *
 * Fields:
 *   mark        1-2 characters for the card tile, since none of these have a
 *               logo we can hotlink without the remote host going down with us.
 *   platform    Short chip: what you need to run it.
 *   blurb       One line, sentence case, for the card.
 *   description Two sentences at most, for the page.
 *   internal    Lives on this site, so it links with Inertia instead of a new
 *               tab and stays out of the "other tools" sidebar.
 */
export const APPS = [
  {
    id: 'id-check',
    name: 'BSUkol ID Check',
    mark: 'ID',
    url: '/id-check',
    host: '/id-check',
    platform: 'On this site',
    internal: true,
    blurb: 'Make a batch-pic style ID poster and share it.',
    description:
      'Build a novelty student card from your own photo, pick a template, and export it ready to post. Nothing is uploaded — the card is drawn in your browser.',
    tags: ['Poster maker', 'No sign-up'],
  },
  {
    id: 'salidumay',
    name: 'Salidumay',
    mark: 'SA',
    url: 'https://salidumay.com/',
    host: 'salidumay.com',
    platform: 'Web app',
    blurb: 'Movies, series, and anime — free to stream.',
    description:
      'Trending films and TV in HD, dubbed and subbed. No account, no paywall.',
    tags: ['Streaming', 'Free'],
  },
  {
    id: 'omaykan',
    name: 'Omaykan',
    mark: 'OM',
    url: 'https://omaykan.com/',
    host: 'omaykan.com',
    platform: 'Web app',
    blurb: 'A free storefront for startup businesses.',
    description:
      'Small sellers get a shop page and an order inbox without paying commission. Shoppers in Baguio and La Trinidad order from home and pay cash or GCash on delivery.',
    tags: ['E-commerce', 'No commission'],
  },
  {
    id: 'kantata',
    name: 'Kantata',
    mark: 'KA',
    url: 'https://kantata.salidumay.com/',
    host: 'kantata.salidumay.com',
    platform: 'Android app',
    blurb: 'Your music, clearly played.',
    description:
      'A local-first music player for the songs already on your phone. Background playback, lock screen controls, gapless, no account.',
    tags: ['Android', 'Offline'],
  },
  {
    id: 'bg-remover',
    name: 'AI Background Remover',
    mark: 'BG',
    url: 'https://bg-remover.salidumay.com/',
    host: 'bg-remover.salidumay.com',
    platform: 'Web app',
    blurb: 'Drop an image, get it back without the background.',
    description:
      'Cuts the subject out of a photo in a couple of seconds. Useful for ID pictures, posters, and product shots.',
    tags: ['AI', 'Images'],
  },
  {
    id: 'gpx2video',
    name: 'GPX to Video',
    mark: 'GP',
    url: 'https://gpx2video.salidumay.com/',
    host: 'gpx2video.salidumay.com',
    platform: 'Web app',
    blurb: 'Turn a GPS track into a shareable route video.',
    description:
      'Feed it the GPX file from a ride, run, or hike and it renders the route as a video you can post.',
    tags: ['Maps', 'Video'],
  },
];

/** Apps that live on this site — linked with Inertia, not a new tab. */
export const SITE_APPS = APPS.filter((app) => app.internal);

/** Everything hosted elsewhere. The sidebar panel and /tools both use this. */
export const EXTERNAL_APPS = APPS.filter((app) => !app.internal);
