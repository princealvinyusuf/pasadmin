document.addEventListener('DOMContentLoaded', function() {
    const jobForm = document.getElementById('job-form');
    const jobsTableBody = document.querySelector('#jobs-table tbody');
    const cancelEditBtn = document.getElementById('cancel-edit');
    let editing = false;
    const bulkInput = document.getElementById('bulk-upload-input');
    const bulkBtn = document.getElementById('bulk-upload-btn');
    const bulkStatus = document.getElementById('bulk-upload-status');

    function fetchJobs() {
        let searchParam = '';
        const searchInput = document.getElementById('search-input');
        if (searchInput && searchInput.value.trim() !== '') {
            searchParam = '&search=' + encodeURIComponent(searchInput.value.trim());
        }
        const uidInput = document.getElementById('uid');
        if (uidInput && uidInput.value.trim() !== '') {
            searchParam += '&uid=' + encodeURIComponent(uidInput.value.trim());
        }
        fetch('jobs.php?' + searchParam.replace(/^&/, ''))
            .then(res => res.json())
            .then(data => {
                const jobs = Array.isArray(data) ? data : (data.jobs || []);
                jobsTableBody.innerHTML = '';
                // Dashboard card elements
                // const totalJobsEl = document.getElementById('total-jobs');
                // const openJobsEl = document.getElementById('open-jobs');
                // const closedJobsEl = document.getElementById('closed-jobs');
                // Count jobs
                let total = jobs.length;
                let open = 0;
                let closed = 0;
                const today = new Date();
                jobs.forEach(job => {
                    // Render table row
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>
                            <button class="btn btn-outline-primary btn-sm me-1" onclick='editJob(${JSON.stringify(job)})'><i class="bi bi-pencil"></i> Edit</button>
                            <button class="btn btn-outline-danger btn-sm" onclick="deleteJob(${job.id})"><i class="bi bi-trash"></i> Delete</button>
                        </td>
                        <td>${job.uid || ''}</td>
                        <td>${job.title || ''}</td>
                        <td>${job.company_name || ''}</td>
                        <td>${job.location || ''}</td>
                        <td>${job.province || ''}</td>
                        <td>${job.city || ''}</td>
                        <td>${job.amount_info || ''}</td>
                        <td>${job.posting_date || ''}</td>
                        <td>${job.scraping_date || ''}</td>
                        <td>${job.job_source || ''}</td>
                        <td>${job.source_type || ''}</td>
                        <td>${job.platform || ''}</td>
                        <td>${job.method_info || ''}</td>
                        <td>${job.active_jobs || ''}</td>
                        <td>${job.inactive_jobs || ''}</td>
                        <td>${job.gender || ''}</td>
                        <td>${job.people_condition || ''}</td>
                        <td>${job.experience_required || ''}</td>
                        <td>${job.insurance || ''}</td>
                        <td>${job.kbli_One_code || ''}</td>
                        <td>${job.kbli_One_desc || ''}</td>
                        <td>${job.kbli_Five_code || ''}</td>
                        <td>${job.kbli_Five_desc || ''}</td>
                        <td>${job.kbji_One_code || ''}</td>
                        <td>${job.kbji_One_desc || ''}</td>
                        <td>${job.kbji_Four_code || ''}</td>
                        <td>${job.kbji_Four_desc || ''}</td>
                        <td>${job.employment_type || ''}</td>
                        <td>${job.experience_level || ''}</td>
                        <td>${job.salary || ''}</td>
                        <td>${job.application_deadline || ''}</td>
                        <td>${job.created_at || ''}</td>
                    `;
                    jobsTableBody.appendChild(tr);
                    // Count open/closed
                    if (job.application_deadline) {
                        const deadline = new Date(job.application_deadline);
                        // Set time to end of day for deadline
                        deadline.setHours(23,59,59,999);
                        if (deadline >= today) {
                            open++;
                        } else {
                            closed++;
                        }
                    } else {
                        closed++;
                    }
                });
                // Update dashboard cards (REMOVED, now only fetchJobCounts does this)
                // if (totalJobsEl) totalJobsEl.textContent = total;
                // if (openJobsEl) openJobsEl.textContent = open;
                // if (closedJobsEl) closedJobsEl.textContent = closed;
            });
    }

    function fetchJobCounts() {
        fetch('jobs.php?counts=1')
            .then(res => res.json())
            .then(counts => {
                const totalJobsEl = document.getElementById('total-jobs');
                const openJobsEl = document.getElementById('open-jobs');
                const closedJobsEl = document.getElementById('closed-jobs');
                if (totalJobsEl) totalJobsEl.textContent = counts.total;
                if (openJobsEl) openJobsEl.textContent = counts.open;
                if (closedJobsEl) closedJobsEl.textContent = counts.closed;
            });
    }

    window.editJob = function(job) {
        Object.keys(job).forEach(key => {
            const el = document.getElementById(key);
            if (el) el.value = job[key] || '';
        });
        // Explicitly set the job-id hidden field
        document.getElementById('job-id').value = job.id;
        editing = true;
        cancelEditBtn.style.display = 'inline-block';
    };

    window.deleteJob = function(id) {
        if (confirm('Delete this job?')) {
            fetch('jobs.php?id=' + id, { method: 'DELETE' })
                .then(res => res.json())
                .then(() => fetchJobs());
        }
    };

    jobForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const data = {};
        Array.from(jobForm.elements).forEach(el => {
            if (el.id && el.type !== 'submit' && el.type !== 'button') {
                data[el.id] = el.value;
            }
        });
        if (editing) {
            fetch('jobs.php', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(res => res.json())
            .then(() => {
                editing = false;
                jobForm.reset();
                cancelEditBtn.style.display = 'none';
                fetchJobs();
            });
        } else {
            fetch('jobs.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(res => res.json())
            .then(() => {
                jobForm.reset();
                fetchJobs();
            });
        }
    });

    cancelEditBtn.addEventListener('click', function() {
        editing = false;
        jobForm.reset();
        cancelEditBtn.style.display = 'none';
    });

    bulkBtn.addEventListener('click', function() {
        const fileInput = document.getElementById('bulk-upload-input');
        const file = fileInput.files[0];
        const progressContainer = document.getElementById('bulk-upload-progress-container');
        const progressBar = document.getElementById('bulk-upload-progress-bar');
        const status = document.getElementById('bulk-upload-status');

        if (!file) {
            status.textContent = 'Please select a file.';
            return;
        }

        // Show progress bar for parsing
        progressContainer.style.display = 'block';
        progressBar.style.width = '10%';
        progressBar.textContent = 'Parsing...';
        progressBar.setAttribute('aria-valuenow', 10);

        // Determine file type
        const ext = file.name.split('.').pop().toLowerCase();
        let fileType = '';
        if (ext === 'xlsx') fileType = 'xlsx';
        else if (ext === 'csv') fileType = 'csv';
        else {
            status.textContent = 'Unsupported file type!';
            progressContainer.style.display = 'none';
            return;
        }

        // Use Web Worker for parsing
        const worker = new Worker('excelWorker.js');
        worker.onmessage = function(e) {
            const { success, jobs, error } = e.data;
            if (success) {
                // Update progress bar for upload
                progressBar.style.width = '30%';
                progressBar.textContent = 'Uploading...';
                progressBar.setAttribute('aria-valuenow', 30);

                // Prepare data for upload
                const payload = JSON.stringify({ jobs });

                // Use XMLHttpRequest for upload progress
                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'jobs.php?bulk=1', true);
                xhr.setRequestHeader('Content-Type', 'application/json');

                xhr.upload.onprogress = function(e) {
                    if (e.lengthComputable) {
                        // Progress from 30% to 90% for upload
                        const percent = 30 + Math.round((e.loaded / e.total) * 60);
                        progressBar.style.width = percent + '%';
                        progressBar.textContent = percent + '%';
                        progressBar.setAttribute('aria-valuenow', percent);
                    }
                };

                xhr.onload = function() {
                    if (xhr.status === 200) {
                        progressBar.style.width = '100%';
                        progressBar.textContent = '100%';
                        progressBar.setAttribute('aria-valuenow', 100);
                        status.textContent = 'Upload complete!';
                    } else {
                        status.textContent = 'Upload failed!';
                    }
                    setTimeout(() => {
                        progressContainer.style.display = 'none';
                        progressBar.style.width = '0%';
                        progressBar.textContent = '0%';
                        progressBar.setAttribute('aria-valuenow', 0);
                    }, 1500);
                };

                xhr.onerror = function() {
                    status.textContent = 'Upload error!';
                    progressContainer.style.display = 'none';
                };

                xhr.send(payload);
            } else {
                status.textContent = 'Error parsing file: ' + error;
                progressContainer.style.display = 'none';
            }
            worker.terminate();
        };
        worker.onerror = function(e) {
            status.textContent = 'Worker error: ' + e.message;
            progressContainer.style.display = 'none';
            worker.terminate();
        };

        // Read file as ArrayBuffer and send to worker
        const reader = new FileReader();
        reader.onload = function(e) {
            worker.postMessage({ fileData: e.target.result, fileType });
        };
        reader.onerror = function() {
            status.textContent = 'Error reading file!';
            progressContainer.style.display = 'none';
        };
        reader.readAsArrayBuffer(file);
    });

    // Export All Job Data
    const exportBtn = document.getElementById('export-all-jobs-btn');
    if (exportBtn) {
        exportBtn.addEventListener('click', function() {
            const exportStatus = document.getElementById('export-status');
            exportStatus.textContent = 'Preparing export...';
            if (typeof XLSX === 'undefined') {
                exportStatus.textContent = 'Export failed: XLSX library not loaded.';
                console.error('XLSX is not defined. Make sure xlsx.full.min.js is loaded before script.js');
                return;
            }
            fetch('jobs.php?export=1')
                .then(res => res.json())
                .then(jobs => {
                    if (!Array.isArray(jobs) || jobs.length === 0) {
                        exportStatus.textContent = 'No job data to export.';
                        return;
                    }
                    // Remove internal fields if needed
                    const jobsForExport = jobs.map(({id, ...rest}) => rest);
                    const ws = XLSX.utils.json_to_sheet(jobsForExport);
                    const wb = XLSX.utils.book_new();
                    XLSX.utils.book_append_sheet(wb, ws, 'Jobs');
                    XLSX.writeFile(wb, 'all_jobs.xlsx');
                    exportStatus.textContent = 'Export successful!';
                })
                .catch((err) => {
                    exportStatus.textContent = 'Export failed.';
                    console.error('Export error:', err);
                });
        });
    }

    fetchJobCounts();
    if (!window.location.pathname.endsWith('index.html')) {
        fetchJobs();
    }

    // Inject shared navigation bar
    function loadNavbar(activePage) {
        fetch('navbar.html')
            .then(res => res.text())
            .then(html => {
                const navDiv = document.getElementById('navbar-placeholder');
                if (navDiv) {
                    navDiv.innerHTML = html;
                    // Set active class
                    if (activePage) {
                        const navId = {
                            'index.html': 'nav-dashboard',
                            'jobs.html': 'nav-jobs',
                            'cron_settings.php': 'nav-settings'
                        }[activePage];
                        if (navId) {
                            document.getElementById(navId)?.classList.add('active');
                            document.getElementById(navId)?.setAttribute('aria-current', 'page');
                        }
                    }
                }
            });
    }
}); 
