# Asset Manager & AI Image Ingestion Implementation

I've successfully designed and integrated the new **Asset Manager** for Helmetsan to handle automated fetching, AI analysis, and ingestion of helmet images from external URLs directly into the catalog.

## Architecture Highlights
The media engine has been fundamentally revamped with the following new modules:

1. **Asset Custom Post Type (CPT)**
   * `includes/CPT/Registrar.php` & `includes/CPT/MetaRegistrar.php`: Introduced an `asset` post type to securely hold images. This changes images from standard WP attachments into strongly typed relational entities linked to one or more Helmets via the `rel_helmets` metadata.
   * `includes/Media/AssetManager.php`: Handles the CRUD operations for these assets and links them structurally to helmets.

2. **Scraper Engine**
   * `includes/Ingestion/ScraperService.php`: Capable of visiting a URL (like Myntra product pages), parsing the JavaScript-rendered data or raw HTML, and extracting the high-res image URLs automatically.

3. **AI Image Analysis**
   * `includes/AI/ImageAnalysisService.php`: Integrates directly with OpenAI's `gpt-4o-mini` vision capabilities. It looks at the scraped photo to determine the precise *type of photo* (e.g., `front-view`, `side-view`, `lifestyle`) and generates a clean, SEO-friendly filename.

4. **Ingestion Orchestrator**
   * `includes/Ingestion/AssetIngestionService.php`: Orchestrates the whole flow: *Scrape -> Download -> Analyze with AI -> Load into WP Media Library -> Create Asset CPT -> Link to Helmet*.

5. **Admin UI**
   * `includes/Admin/AssetManagerAdmin.php`: Registered a new meta box in the backend Helmet editor. You can now simply drop a URL into the input field and click "Import & Analyze," and the system handles the entire fetching and linking workflow dynamically via AJAX.

6. **Cloudflare R2 Integration**
   * Designed a highly scalable storage solution using Cloudflare R2 object storage.
   * Leveraged the `aws/aws-sdk-php` library and created `CloudflareR2Service.php` to handle secure authentication and high-speed multipart uploads.
   * `AssetIngestionService.php` was modified to conditionally bypass local WP media sideloading in favor of direct R2 upload when enabled.
   * Extended the `Helmetsan Settings -> Media Engine` dashboard via `Admin.php` to include input fields for configuring R2 properties (`AccountId`, `AccessKey`, `SecretKey`, `Bucket` and `PublicUrl`). Local links are now efficiently served via Cloudflare domains.

7. **Storage Protection (Relevance Checks)**
   * Built in an `is_relevant` semantic guardrail in the AI Prompt via `ImageAnalysisService`.
   * OpenAI will now inspect scraped images and flag junk assets like size charts, generic logos, or promotional banners.
   * The `AssetIngestionService` explicitly skips uploading these irrelevant files to R2, saving bandwidth, storage costs, and catalog pollution.

## Verification & Usage Instructions

You can immediately test this new "Asset Manager" feature via the WordPress Admin:

1. Go to your **WordPress Dashboard** -> **Helmets** -> Edit any existing helmet (or create a new placeholder one).
2. Scroll to the new **"Asset Manager (AI Ingestion)"** meta box.
3. Paste the provided URL: `https://www.myntra.com/helmet/steelbird/steelbird-fighter-scratch-resistant-full-face-helmet-/38753734/buy`
4. Click **Import & Analyze**.
5. The UI will show a processing message. Behind the scenes, the server will scrape Myntra, send the photos to OpenAI Vision to detect the angles, save them locally, and link them to your helmet. 
6. Once the page reloads, you will see the new AI-assessed images with their specific angles displayed directly in the box! *(Note: Some scrapers might occasionally encounter bot-blocks on Myntra, but the fallback logic is in place).*

## Phase 2: Cloudflare Edge Features

### 1. Background Task Queuing (Cloudflare Queues)
* **Goal**: Decouple heavy asset ingestion tasks to prevent PHP execution timeouts on large catalogs.
* **Implementation Details**:
   * Created `QueueService.php` to securely dispatch ingestion payloads (URLs) directly to Cloudflare's high-throughput Message Queue API.
   * Developed an Edge Worker (`cloudflare-workers/ingestion-worker/src/index.js`) to consume from the queue, execute the heavy scraping & R2 uploads asynchronously, and transmit the final asset URLs back to WordPress.
   * Added `IngestionCallbackController.php` as a secure webhook endpoint to receive the worker's response and finalize the custom post type linking.

### 2. Serverless User Reviews (Workers + D1)
* **Goal**: Replace the bloated, spam-prone WordPress comment system with a lightning-fast, edge-based SQL database via Cloudflare D1.
* **Implementation Details**:
   * Created `src/index.js` Worker script to receive reviews, communicate with D1, and return formatted responses.
   * Defined D1 SQL schema in `schema.sql` mapped to Helmetsan product IDs.

### 3. Privacy-First Cloudflare Web Analytics
* **Goal**: Drop heavy third-party scripts (like Google Analytics) to improve page speed while maintaining edge-based tracking without cookies. 
* **Implementation Details**:
   * Added `cf_analytics_token` to `Config.php` and integrated fields into `Admin.php` under the **Helmetsan Settings -> Analytics** tab.
   * Developed `AnalyticsInjector.php` to automatically hook into `wp_footer` and inject the Cloudflare `beacon.min.js` script with the configured token.
   * Registered `AnalyticsInjector` inside `Plugin.php` using the modern hybrid architecture dependency injection container.
