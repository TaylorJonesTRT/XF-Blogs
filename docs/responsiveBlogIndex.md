# Responsive Blog index

Restyle the **Blog index** (`/blogs/`) so it works on narrow screens. Agreed in a grilling session on 2026-10-27.

**Status:** ready to implement. The version is already bumped to 1.8.0 for this work, so don't bump it again.

## Problems today

- Cards are a fixed `width: 31%` with no breakpoints, so each card is about 109px wide on a phone and titles wrap one word per line.
- `object-fit: cover` is set on the wrapper `div` instead of the `img`, so header images are stretched (`fill`).
- `margin: 0 auto` centres a card that ends up alone on the last row.
- Description length isn't limited, so card heights differ.
- `taylorj_blogs_index.less` has a top-level `h1 {}` rule that affects every `h1` on the page, and each card title is an `h1`.
- `#cbcbcb` and `#185886` are hard-coded instead of using style properties.
- `.taylorjBlogs-blog-view-main` rules in the index stylesheet are dead code, because the blog view uses `taylorj_blogs_blog_view.less`.

## Decisions

1. **Layout:** a grid of cards that adjusts to the screen width, `repeat(auto-fill, minmax(260px, 1fr))` with **at most 3 columns**. With the default `taylorjBlogsPerPage` of 6, that is 3+3 on desktop and 2+2+2 on tablet.
2. **Phone (`max-width: @xf-responsiveNarrow`):** each card becomes a compact row with a square thumbnail on the left and text on the right.
3. **One set of markup for each blog**, rearranged by CSS grid areas. Don't use `taylorj_blogs_list_macros::blog`, because it depends on XFRM (see CONTEXT.md, Flagged ambiguities).
4. **Card content:** header image (or a `fa-book` placeholder on a style-property background), title as `h2`, description limited to 2 lines, and a meta line: author name · blog post count · last post date. A blog with no posts shows "No posts yet". The avatar overlapping the banner stays on desktop cards and is hidden in phone rows.
5. **Thumbnail:** the header image cropped square with `object-fit: cover`. The image isn't resized on upload, so no new image variant is needed.
6. **Tap target:** the whole card, using `data-xf-init="touch-proxy"` with the title link as `data-tp-primary="on"`. The author name stays a separate link.

## Also in scope (same template, small)

- Rewrite `taylorj_blogs_index.less` from scratch with no global selectors, style-property colours, and the dead blog-view rules removed.
- Add `->with('User')` to the index finder in `Pub/Controller/Blogs::actionIndex` so showing author names doesn't add a query per blog.
- Show an empty state when `$blogs` is empty.
- Only show the "Create a blog" page action when the visitor has `taylorjBlogs.canCreate`. Today it always shows and links to a no-permission page.
- Remove the stray `data="{$blog}"` from `<xf:pagenav>`.
- New phrase(s): "No posts yet", plus possibly "{count} posts" if `taylorj_blogs_blog_post_count` doesn't fit inline.

## Out of scope

- Removing the XFRM dependency from `taylorj_blogs_list_macros` and `blog_icon()`. This is a separate follow-up.
- Generating resized header image variants.
- A grid/list toggle.

## Files

- `_output/templates/public/taylorj_blogs_index.html`
- `_output/templates/public/taylorj_blogs_index.less`
- `Pub/Controller/Blogs.php`
- `_data/phrases.xml` (via `xf xf-dev:import` / export)

## Verification

Check `/blogs/` in Chrome at desktop width, tablet width (about 700px) and phone width (about 375px), and with 0 blogs, 1 blog, 4 blogs, and a blog with no posts and no header image. Check the light and dark styles.
