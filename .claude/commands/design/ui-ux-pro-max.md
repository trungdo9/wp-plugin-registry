---
description: Advanced UI/UX design with 50 styles, 21 palettes, 50 fonts, and comprehensive design system
argument-hint: [product-type] [style] [industry]
---

# UI/UX Pro Max - Design Intelligence

Advanced UI/UX design workflow with comprehensive style database, color palettes, font pairings, and best practices.

## When to Use

When user requests UI/UX work (design, build, create, implement, review, fix, improve) and needs:
- Comprehensive design system
- Multiple style options (50+ styles)
- Color palettes (21 palettes)
- Font pairings (50 fonts)
- UX best practices and anti-patterns

---

## Step 1: Analyze User Requirements

Extract key information from user request:
- **Product type**: SaaS, e-commerce, portfolio, dashboard, landing page, etc.
- **Style keywords**: minimal, playful, professional, elegant, dark mode, brutalism, etc.
- **Industry**: healthcare, fintech, gaming, education, beauty, etc.
- **Stack**: React, Vue, Next.js, or default to `html-tailwind`

---

## Step 2: Apply Design Knowledge

Use the following design knowledge to create comprehensive UI:

### Available Styles (50+)

| Style | Characteristics |
|-------|-----------------|
| Minimalism | Clean, whitespace, essential elements only |
| Brutalism | Raw, bold, unconventional layouts |
| Glassmorphism | Frosted glass, translucency, blur effects |
| Neumorphism | Soft shadows, embossed/debossed elements |
| Dark Mode | Dark backgrounds, light text, accent colors |
| Neobrutalism | Bold colors, thick borders, shadow offsets |
| Retro/Vintage | Warm colors, serif fonts, grain textures |
| Cyberpunk | Neon colors, glitch effects, futuristic |
| Swiss Style | Grid-based, sans-serif, bold typography |
| Flat Design | Simple shapes, no shadows, vibrant colors |
| Material Design | Cards, shadows, responsive motion |
| Skeuomorphism | Realistic textures, shadows, depth |
| And 39 more... |

### Available Color Palettes (21)

| Palette | Use Cases |
|---------|-----------|
| SaaS Blue | Tech, B2B, enterprise software |
| E-commerce | Retail, marketplace, shopping |
| Healthcare | Medical, wellness, fitness |
| Beauty/Spa | Beauty, wellness, salon |
| Fintech | Finance, banking, crypto |
| Dark Tech | Gaming, developer tools, SaaS |
| Luxury | High-end, premium brands |
| Nature | Eco, organic, sustainable |
| Education | Learning, courses, training |
| Food/Dining | Restaurants, food delivery |
| Travel | Tourism, hotels, booking |
| Real Estate | Property, housing, architecture |
| Entertainment | Media, streaming, gaming |
| And 10 more... |

### Available Font Pairings (50)

| Category | Font Examples |
|----------|--------------|
| Elegant/Luxury | Playfair Display + Lato |
| Modern/Tech | Inter + Roboto Mono |
| Professional | Montserrat + Open Sans |
| Playful | Quicksand + Nunito |
| Editorial | Merriweather + Source Sans Pro |
| Minimal | DM Sans + DM Sans |
| Brutalist | Space Grotesk + Space Mono |
| Classic | Cormorant Garamond + Proza Libre |
| And 43 more... |

---

## Step 3: Design by Domain

### For Product/SaaS
- Card-based layouts
- Dashboard components
- Data visualization focus
- Clean navigation

### For E-commerce
- Product galleries
- Shopping cart flow
- Trust signals
- Clear CTAs

### For Portfolio
- Showcase layouts
- Case study format
- Project filtering
- About sections

### For Landing Pages
- Hero-centric design
- Social proof
- Feature highlights
- Pricing tables

### For Dashboard/Analytics
- Chart visualizations
- Data tables
- Real-time updates
- Navigation sidebar

---

## Step 4: Stack Guidelines

Default to `html-tailwind` if user doesn't specify:

| Stack | Guidelines |
|-------|------------|
| **html-tailwind** (DEFAULT) | Tailwind utilities, responsive, accessibility |
| **react** | Component-based, hooks, state management |
| **nextjs** | Server components, routing, image optimization |
| **vue** | Composition API, Pinia, Vue Router |
| **svelte** | Runes, stores, SvelteKit |
| **swiftui** | Native iOS views, state, navigation |
| **react-native** | Cross-platform mobile, navigation |
| **flutter** | Widgets, state, theming |
| **shadcn** | Radix UI + Tailwind, accessible components |

---

## Step 5: UX Best Practices

### Icons & Visual Elements

| Rule | Do | Don't |
|------|-----|-------|
| **No emoji icons** | Use SVG icons (Heroicons, Lucide) | Use emojis as UI icons |
| **Stable hover states** | Color/opacity transitions | Scale transforms that shift layout |
| **Consistent icon sizing** | Fixed 24x24 viewBox with w-6 h-6 | Mix different icon sizes |

### Interaction & Cursor

| Rule | Do | Don't |
|------|-----|-------|
| **Cursor pointer** | Add `cursor-pointer` to clickable cards | Default cursor on interactive elements |
| **Hover feedback** | Visual feedback (color, shadow, border) | No indication of interactivity |
| **Smooth transitions** | `transition-colors duration-200` | Instant changes or too slow (>500ms) |

### Light/Dark Mode Contrast

| Rule | Do | Don't |
|------|-----|-------|
| **Glass card light mode** | Use `bg-white/80` or higher | Use `bg-white/10` (too transparent) |
| **Text contrast light** | Use `#0F172A` (slate-900) | Use `#94A3B8` (slate-400) |
| **Muted text light** | Use `#475569` (slate-600) minimum | Use gray-400 or lighter |
| **Border visibility** | Use `border-gray-200` in light mode | Use `border-white/10` (invisible) |

### Layout & Spacing

| Rule | Do | Don't |
|------|-----|-------|
| **Floating navbar** | Add `top-4 left-4 right-4` spacing | Stick navbar to `top-0 left-0 right-0` |
| **Content padding** | Account for fixed navbar height | Content hidden behind fixed elements |
| **Consistent max-width** | Use same `max-w-6xl` or `max-w-7xl` | Mix different container widths |

---

## Step 6: Pre-Delivery Checklist

Before delivering UI code, verify:

### Visual Quality
- [ ] No emojis used as icons (use SVG instead)
- [ ] All icons from consistent icon set (Heroicons/Lucide)
- [ ] Hover states don't cause layout shift

### Interaction
- [ ] All clickable elements have `cursor-pointer`
- [ ] Hover states provide clear visual feedback
- [ ] Transitions are smooth (150-300ms)
- [ ] Focus states visible for keyboard navigation

### Light/Dark Mode
- [ ] Light mode text has sufficient contrast (4.5:1 minimum)
- [ ] Glass/transparent elements visible in light mode
- [ ] Borders visible in both modes
- [ ] Test both modes before delivery

### Layout
- [ ] Floating elements have proper spacing from edges
- [ ] No content hidden behind fixed navbars
- [ ] Responsive at 320px, 768px, 1024px, 1440px
- [ ] No horizontal scroll on mobile

### Accessibility
- [ ] All images have alt text
- [ ] Form inputs have labels
- [ ] Color is not the only indicator
- [ ] `prefers-reduced-motion` respected

---

## Example Workflow

**User request:** "Landing page cho dịch vụ chăm sóc da chuyên nghiệp"

**AI should:**
1. Select appropriate style (elegant, minimal, soft)
2. Choose beauty/spa color palette
3. Select elegant font pairing
4. Design hero-centric landing page structure
5. Include: hero section, services, testimonials, pricing, CTA
6. Apply UX best practices for beauty industry
7. Ensure mobile responsiveness
8. Verify all pre-delivery checklist items

---

## Output

Provide comprehensive UI implementation with:
- Clean, semantic HTML structure
- Tailwind CSS classes (or specified stack)
- Responsive design for all screen sizes
- Proper dark/light mode support
- Accessible markup
- Professional icon usage
- Smooth animations and transitions
