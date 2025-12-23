import React, { useState } from 'react';

/**
 * OVO (Online Vacancy Outlook) - Single-file React UI prototype
 * - TailwindCSS utility classes are used for styling (no Tailwind imports here).
 * - This file is a fully self-contained design & developer spec for your frontend team.
 * - Default export is the App component.
 *
 * What this provides:
 *  - Top-level layout with side navigation and role-aware pages
 *  - Pages: Dashboard, Scraper Manager, Raw DB, Transform Pipeline, Clean DB, Reports & Publish, Settings, Logs
 *  - Example components: job list, cron/schedule editor, transformation rule builder, manual input form
 *  - Developer notes: suggested API endpoints, data models, and build/print checklist at the bottom
 *
 * How to use:
 *  - Drop into a React + Tailwind project, wire components to real APIs, and replace mock data with real fetches.
 *  - This is meant as a build-print: all major interactive elements and flows are represented.
 */

// ---------- Mock data (replace with API calls in real build) ----------
const mockScrapers = [
  { id: 's1', name: 'JobPortal A', enabled: true, cron: '0 */1 * * *', lastRun: '2025-12-03 12:10', lastStatus: 'OK' },
  { id: 's2', name: 'JobPortal B', enabled: true, cron: '0 0 */3 * *', lastRun: '2025-12-03 09:00', lastStatus: 'OK' },
  { id: 's3', name: 'Karihub Manual Upload', enabled: false, cron: '', lastRun: '-', lastStatus: 'Manual' },
];

const mockRawRecords = new Array(8).fill(0).map((_, i) => ({
  id: `raw-${i+1}`,
  source: i % 2 === 0 ? 'JobPortal A' : 'JobPortal B',
  title: ['Sales', 'Software Engineer', 'Driver', 'Teacher'][i%4],
  location: ['Jakarta', 'Surabaya', 'Bandung', 'Medan'][i%4],
  posted_at: `2025-12-0${(i%9)+1}`,
  salary: i % 3 === 0 ? '' : `${(3 + i)*1000000}`,
  raw_html_snippet: '<div>Job content snippet...</div>',
}));

const mockTransformRules = [
  { id: 'r1', name: 'Normalize Province Names', description: 'Map "JKT" => "Jakarta"', enabled: true },
  { id: 'r2', name: 'Deduplicate by title+company+location', description: 'Keep newest', enabled: true },
];

const mockCleanRecords = mockRawRecords.slice(0,5).map((r, idx) => ({ ...r, cleaned_at: `2025-12-03 13:${10+idx}`, standardized_title: r.title.toLowerCase(), kbli: '6201', kbji: 'X' }));

// ---------- Helper small components ----------
function Icon({ name }){
  const map = {
    dashboard: '📊', scraper: '🕸️', raw: '🧱', transform: '⚙️', clean: '🧹', report: '📣', settings: '⚙️', logs: '📜'
  };
  return <span className="mr-2">{map[name] || '🔹'}</span>;
}

function Badge({ children, color='gray' }){
  const cls = `inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-${color}-100 text-${color}-800`;
  // note: Tailwind dynamic colors in class strings won't compile in some setups; replace with fixed classes in real build
  return <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">{children}</span>;
}

// ---------- Page components ----------
function DashboardPage(){
  return (
    <div className="p-6">
      <h2 className="text-2xl font-bold mb-4">OVO Dashboard</h2>
      <div className="grid grid-cols-3 gap-4 mb-6">
        <div className="p-4 border rounded-lg shadow-sm">
          <h3 className="text-sm text-gray-500">Raw vacancies</h3>
          <div className="text-2xl font-semibold">{mockRawRecords.length}</div>
          <div className="text-xs text-gray-400">Updated: 2025-12-03 13:00</div>
        </div>
        <div className="p-4 border rounded-lg shadow-sm">
          <h3 className="text-sm text-gray-500">Clean vacancies</h3>
          <div className="text-2xl font-semibold">{mockCleanRecords.length}</div>
          <div className="text-xs text-gray-400">Processed today: 5</div>
        </div>
        <div className="p-4 border rounded-lg shadow-sm">
          <h3 className="text-sm text-gray-500">Active scrapers</h3>
          <div className="text-2xl font-semibold">{mockScrapers.filter(s=>s.enabled).length}</div>
          <div className="text-xs text-gray-400">Next run: in 50m</div>
        </div>
      </div>

      <div className="grid grid-cols-2 gap-4">
        <div className="p-4 border rounded-lg">
          <h4 className="font-medium mb-2">Recent transform jobs</h4>
          <ul className="text-sm text-gray-700">
            <li>2025-12-03 12:50 — Transform batch #342 — <Badge>Success</Badge></li>
            <li>2025-12-03 11:20 — Deduplication run — <Badge>Success</Badge></li>
            <li>2025-12-03 10:01 — NLP classification — <Badge>Warning</Badge></li>
          </ul>
        </div>
        <div className="p-4 border rounded-lg">
          <h4 className="font-medium mb-2">Publish status</h4>
          <p className="text-sm text-gray-700">Last published report to Tableau: 2025-12-02 18:00</p>
        </div>
      </div>
    </div>
  );
}

function ScraperManagerPage(){
  const [scrapers, setScrapers] = useState(mockScrapers);
  function toggleEnabled(id){
    setScrapers(scrapers.map(s=> s.id===id ? {...s, enabled: !s.enabled} : s));
  }
  function addScraper(){
    const newS = { id: `s${scrapers.length+1}`, name: 'New Portal', enabled: false, cron: '0 0 * * *', lastRun: '-', lastStatus: 'Never' };
    setScrapers([...scrapers, newS]);
  }

  return (
    <div className="p-6">
      <div className="flex items-center justify-between mb-4">
        <h2 className="text-2xl font-bold">Scraping Manager</h2>
        <div>
          <button onClick={addScraper} className="px-3 py-1 rounded bg-indigo-600 text-white">Add Scraper</button>
        </div>
      </div>

      <div className="grid gap-3">
        {scrapers.map(s => (
          <div key={s.id} className="flex items-center justify-between p-3 border rounded">
            <div>
              <div className="font-medium">{s.name}</div>
              <div className="text-xs text-gray-500">Cron: {s.cron || '—'} · Last: {s.lastRun} · {s.lastStatus}</div>
            </div>
            <div className="flex items-center gap-2">
              <input type="text" defaultValue={s.cron} className="border rounded px-2 py-1 text-sm" />
              <button onClick={()=>toggleEnabled(s.id)} className={`px-3 py-1 rounded ${s.enabled? 'bg-red-500 text-white' : 'bg-green-600 text-white'}`}>{s.enabled ? 'Disable' : 'Enable'}</button>
              <button className="px-3 py-1 rounded border">Run now</button>
              <button className="px-3 py-1 rounded border">Edit</button>
            </div>
          </div>
        ))}
      </div>

      <div className="mt-6 p-4 border rounded">
        <h3 className="font-medium mb-2">Manual Input (Karihub / other)</h3>
        <form className="grid grid-cols-2 gap-2">
          <input placeholder="Company" className="p-2 border rounded" />
          <input placeholder="Job title" className="p-2 border rounded" />
          <input placeholder="City/Province" className="p-2 border rounded" />
          <textarea placeholder="Description / raw text" className="p-2 border rounded col-span-2" />
          <div className="col-span-2 text-right">
            <button className="px-4 py-2 bg-indigo-600 text-white rounded">Submit manual job</button>
          </div>
        </form>
      </div>

      <div className="mt-6">
        <h3 className="text-lg font-semibold mb-2">Cron job status / scheduler</h3>
        <div className="p-4 border rounded">
          <p className="text-sm">Suggestion: Use a centralized cron manager (e.g., Airflow / Prefect / Kubernetes CronJob). Store schedules in DB and expose an API to start/stop immediate runs.</p>
        </div>
      </div>
    </div>
  );
}

function RawDBPage(){
  const [records] = useState(mockRawRecords);
  return (
    <div className="p-6">
      <h2 className="text-2xl font-bold mb-4">Raw Database (staging)</h2>
      <div className="mb-4 flex gap-2">
        <input placeholder="Search raw records..." className="border p-2 rounded w-1/3" />
        <select className="border p-2 rounded">
          <option>All sources</option>
          <option>JobPortal A</option>
          <option>JobPortal B</option>
        </select>
        <button className="px-3 py-1 rounded border">Export CSV</button>
      </div>

      <div className="overflow-auto border rounded">
        <table className="min-w-full text-left">
          <thead className="bg-gray-50">
            <tr>
              <th className="px-3 py-2">ID</th>
              <th className="px-3 py-2">Source</th>
              <th className="px-3 py-2">Title</th>
              <th className="px-3 py-2">Location</th>
              <th className="px-3 py-2">Salary</th>
              <th className="px-3 py-2">Actions</th>
            </tr>
          </thead>
          <tbody>
            {records.map(r=> (
              <tr key={r.id} className="border-t">
                <td className="px-3 py-2 text-sm">{r.id}</td>
                <td className="px-3 py-2 text-sm">{r.source}</td>
                <td className="px-3 py-2 text-sm">{r.title}</td>
                <td className="px-3 py-2 text-sm">{r.location}</td>
                <td className="px-3 py-2 text-sm">{r.salary || '—'}</td>
                <td className="px-3 py-2 text-sm">
                  <button className="px-2 py-1 border rounded text-xs mr-2">View</button>
                  <button className="px-2 py-1 border rounded text-xs">Flag</button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

function TransformPipelinePage(){
  const [rules, setRules] = useState(mockTransformRules);
  function addRule(){
    const newR = { id: `r${rules.length+1}`, name: 'New Rule', description: '', enabled: false };
    setRules([...rules, newR]);
  }
  return (
    <div className="p-6">
      <h2 className="text-2xl font-bold mb-4">Data Transformation Pipeline</h2>
      <div className="mb-4 p-4 border rounded">
        <h4 className="font-medium">Pipeline steps (suggested)</h4>
        <ol className="list-decimal pl-6 text-sm text-gray-700">
          <li>Cleansing (remove HTML, normalize whitespace)</li>
          <li>Normalization (province, city, job level, gender, salary formats)</li>
          <li>Deduplication (title+company+location timestamps)</li>
          <li>Standardization (KBJI/KBLI mapping, job types)</li>
          <li>Classification (NLP model: KBJI, KBLI, Skills extraction)</li>
        </ol>
      </div>

      <div className="mb-4">
        <div className="flex items-center justify-between mb-2">
          <h4 className="font-medium">Transformation rules</h4>
          <button onClick={addRule} className="px-3 py-1 rounded bg-indigo-600 text-white">Add rule</button>
        </div>
        <div className="grid gap-2">
          {rules.map(r => (
            <div key={r.id} className="p-3 border rounded flex items-center justify-between">
              <div>
                <div className="font-medium">{r.name}</div>
                <div className="text-xs text-gray-500">{r.description}</div>
              </div>
              <div>
                <label className="inline-flex items-center">
                  <input type="checkbox" defaultChecked={r.enabled} className="mr-2" /> Enabled
                </label>
                <button className="ml-3 px-2 py-1 border rounded">Edit</button>
              </div>
            </div>
          ))}
        </div>
      </div>

      <div className="p-4 border rounded">
        <h4 className="font-medium mb-2">Manual transform run</h4>
        <div className="flex gap-2">
          <button className="px-3 py-1 rounded bg-green-600 text-white">Run pipeline on all raw</button>
          <button className="px-3 py-1 rounded border">Run on selection</button>
          <button className="px-3 py-1 rounded border">Preview changes (dry run)</button>
        </div>
      </div>
    </div>
  );
}

function CleanDBPage(){
  const [clean] = useState(mockCleanRecords);
  return (
    <div className="p-6">
      <h2 className="text-2xl font-bold mb-4">Database Clean (canonical)</h2>
      <div className="mb-3 text-sm text-gray-600">This is the canonical dataset used by reporting and publishing systems (Tableau, APIs)</div>
      <div className="overflow-auto border rounded">
        <table className="min-w-full text-left">
          <thead className="bg-gray-50">
            <tr>
              <th className="px-3 py-2">ID</th>
              <th className="px-3 py-2">Title</th>
              <th className="px-3 py-2">Std Title</th>
              <th className="px-3 py-2">KBLI</th>
              <th className="px-3 py-2">Cleaned at</th>
              <th className="px-3 py-2">Actions</th>
            </tr>
          </thead>
          <tbody>
            {clean.map(c=> (
              <tr key={c.id} className="border-t">
                <td className="px-3 py-2 text-sm">{c.id}</td>
                <td className="px-3 py-2 text-sm">{c.title}</td>
                <td className="px-3 py-2 text-sm">{c.standardized_title}</td>
                <td className="px-3 py-2 text-sm">{c.kbli}</td>
                <td className="px-3 py-2 text-sm">{c.cleaned_at}</td>
                <td className="px-3 py-2 text-sm">
                  <button className="px-2 py-1 border rounded text-xs mr-2">Re-classify</button>
                  <button className="px-2 py-1 border rounded text-xs">Export</button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <div className="mt-4 p-4 border rounded">
        <h4 className="font-medium">Publish / API</h4>
        <p className="text-sm text-gray-700">This dataset should be exposed via a versioned API and ETL push to Tableau / reporting tools.</p>
        <div className="mt-2">
          <button className="px-3 py-1 rounded bg-indigo-600 text-white mr-2">Push to Tableau</button>
          <button className="px-3 py-1 rounded border">Download snapshot</button>
        </div>
      </div>
    </div>
  );
}

function ReportsPage(){
  return (
    <div className="p-6">
      <h2 className="text-2xl font-bold mb-4">Reports & Publication</h2>
      <div className="grid grid-cols-2 gap-4">
        <div className="p-4 border rounded">
          <h4 className="font-medium">Tools / Dashboard</h4>
          <p className="text-sm text-gray-700">Link: Tableau / Looker / internal BI. Provide scheduled extracts and row-level security for public vs internal dashboards.</p>
          <div className="mt-2">
            <button className="px-3 py-1 rounded bg-indigo-600 text-white">Open Tableau</button>
          </div>
        </div>
        <div className="p-4 border rounded">
          <h4 className="font-medium">OVO Report Builder</h4>
          <p className="text-sm text-gray-700">Create scheduled OVO public reports (CSV / PDF) and set publication cadence.</p>
          <div className="mt-2">
            <button className="px-3 py-1 rounded border">New scheduled report</button>
          </div>
        </div>
      </div>
    </div>
  );
}

function SettingsPage(){
  return (
    <div className="p-6">
      <h2 className="text-2xl font-bold mb-4">Settings & Users</h2>
      <div className="grid grid-cols-2 gap-4">
        <div className="p-4 border rounded">
          <h4 className="font-medium">User management</h4>
          <p className="text-sm text-gray-700">Roles: SuperAdmin, DataEngineer, LMDataExpert, ScraperAdmin, Viewer</p>
          <div className="mt-2">
            <button className="px-3 py-1 rounded border">Manage users</button>
          </div>
        </div>
        <div className="p-4 border rounded">
          <h4 className="font-medium">Integrations</h4>
          <p className="text-sm text-gray-700">Tableau, S3/Cloud storage, DB credentials, NLP model endpoints, Scheduler (Airflow)</p>
          <div className="mt-2">
            <button className="px-3 py-1 rounded border">Edit Integrations</button>
          </div>
        </div>
      </div>
    </div>
  );
}

function LogsPage(){
  return (
    <div className="p-6">
      <h2 className="text-2xl font-bold mb-4">System Logs & Audits</h2>
      <div className="p-4 border rounded">
        <p className="text-sm">Search logs, filter by source (scraper, transform, publish), and export for audits. This view is critical for anti-corruption transparency — keep immutable logs and RBAC for access.</p>
      </div>
    </div>
  );
}

// ---------- App shell & Navigation ----------
const NAV_ITEMS = [
  { id: 'dashboard', label: 'Dashboard', icon: 'dashboard' },
  { id: 'scrapers', label: 'Scrapers', icon: 'scraper' },
  { id: 'raw', label: 'Raw DB', icon: 'raw' },
  { id: 'transform', label: 'Transform', icon: 'transform' },
  { id: 'clean', label: 'Clean DB', icon: 'clean' },
  { id: 'reports', label: 'Reports', icon: 'report' },
  { id: 'logs', label: 'Logs', icon: 'logs' },
  { id: 'settings', label: 'Settings', icon: 'settings' },
];

export default function App(){
  const [page, setPage] = useState('dashboard');
  const [sidebarCollapsed, setSidebarCollapsed] = useState(false);

  return (
    <div className="h-screen flex bg-gray-50 text-gray-800">
      <aside className={`transition-all duration-150 bg-white border-r ${sidebarCollapsed ? 'w-20' : 'w-64'}`}>
        <div className="p-4 flex items-center justify-between border-b">
          <div className="flex items-center">
            <div className="text-2xl mr-2">OVO</div>
            {!sidebarCollapsed && <div className="text-sm text-gray-500">Online Vacancy Outlook</div>}
          </div>
          <button onClick={()=>setSidebarCollapsed(!sidebarCollapsed)} className="text-sm px-2 py-1 rounded border">{sidebarCollapsed ? '>' : '<'}</button>
        </div>
        <nav className="p-3">
          {NAV_ITEMS.map(n => (
            <div key={n.id} className={`flex items-center cursor-pointer p-2 rounded hover:bg-gray-100 ${page===n.id ? 'bg-gray-100' : ''}`} onClick={()=>setPage(n.id)}>
              <Icon name={n.icon} />
              {!sidebarCollapsed && <span>{n.label}</span>}
            </div>
          ))}
        </nav>

        <div className="absolute bottom-4 left-0 right-0 p-3 text-xs text-gray-500">
          <div>Version: 1.0</div>
          <div className="mt-2">User: Data Engineer</div>
        </div>
      </aside>

      <main className="flex-1 overflow-auto">
        <header className="p-3 bg-white border-b flex items-center justify-between">
          <div className="flex items-center gap-4">
            <h1 className="text-lg font-semibold">{page.toUpperCase()}</h1>
            <div className="text-sm text-gray-500">Connected: Staging</div>
          </div>
          <div className="flex items-center gap-3">
            <button className="px-3 py-1 rounded border">Notifications</button>
            <img src="https://placehold.co/32" alt="avatar" className="rounded-full" />
          </div>
        </header>

        <section>
          {page === 'dashboard' && <DashboardPage />}
          {page === 'scrapers' && <ScraperManagerPage />}
          {page === 'raw' && <RawDBPage />}
          {page === 'transform' && <TransformPipelinePage />}
          {page === 'clean' && <CleanDBPage />}
          {page === 'reports' && <ReportsPage />}
          {page === 'settings' && <SettingsPage />}
          {page === 'logs' && <LogsPage />}
        </section>

      </main>
    </div>
  );
}

/*

--- Developer build notes & API contract (copy into repository README for devs) ---

Frontend responsibilities:
 - Provide management UI for scraping jobs (create/edit schedules, enable/disable, run now)
 - Manual input form for Karihub or other manual sources
 - Browse raw staging DB and allow flagging / exporting
 - Manage transformation rules and run the pipeline with dry-run preview
 - Review and correct canonical (clean) records, re-run classification
 - Configure and schedule reports and publish to Tableau or S3
 - View logs & audit trail (RBAC protected)

Suggested API endpoints (example REST):
 - GET /api/scrapers -> list scrapers
 - POST /api/scrapers -> create scraper config
 - PUT /api/scrapers/:id -> update config
 - POST /api/scrapers/:id/run -> trigger immediate run
 - GET /api/raw?source=&page=&q= -> raw records (staging)
 - POST /api/raw/bulk-import -> manual upload (Karihub)
 - GET /api/transform/rules -> list rules
 - POST /api/transform/rules -> create rule
 - POST /api/transform/run -> run pipeline (body: { dryRun: true/false, selection: [] })
 - GET /api/clean -> canonical records
 - POST /api/publish/tableau -> push snapshot
 - GET /api/logs?source=&level=&from=&to= -> system logs

Suggested DB schema (high-level):
 - raw_vacancies: id, source, source_id, raw_html, extracted_fields (JSON), fetched_at, status, flags
 - transform_jobs: id, started_at, finished_at, stats (JSON), status
 - clean_vacancies: id, canonical_fields (JSON), kbli, kbji, normalized_location, standardized_title, cleaned_at
 - scrapers: id, name, config (JSON), cron_expr, enabled, last_run_at, last_status
 - users: id, name, email, role
 - audit_logs: id, actor_id, action, object_type, object_id, timestamp, details

Model / ML endpoints:
 - POST /api/nlp/classify (body: text) -> { kbji, kbli, skills: [], confidence }
 - POST /api/nlp/extract_salaries (body: text) -> { min, max, currency }

Security & infra notes:
 - RBAC for each page and action. Enforce server-side checks.
 - All actions that modify canonical data must be audited.
 - Use S3 + DB snapshots for exports. Prefer incremental exports for dashboards.
 - Scheduler: Airflow / Prefect or Kubernetes CronJobs recommended.
 - Store logs in an immutable store (Cloud Logging / ELK) for transparency.

Print / Handover checklist for devs & designers:
 1. Create React repo with Tailwind configured.
 2. Put this single-file prototype into src/pages/OVOPrototype.jsx for reference.
 3. Implement API stubs and wire to the UI using fetch/axios.
 4. Implement authentication and RBAC on API layer.
 5. Create responsive behaviors and test on tablet & mobile.
 6. Prepare a design handoff with colors, fonts, and component library (shadcn/ui suggested).
 7. Provide user acceptance test (UAT) scenarios for each role (ScraperAdmin, DataEngineer, LMDataExpert).

*/
