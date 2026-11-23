/* =====================================================================
   Assignment form:
   - hours still available for the chosen course and type (BR5)
   - teacher workload checked live with fetch() (BR6)
   - course list filtered by search (fetch)
   ===================================================================== */

(() => {
    const form = document.getElementById('assignmentForm');
    if (!form) return;

    const course = document.getElementById('course_id');
    const type = document.getElementById('type');
    const teacher = document.getElementById('teacher_id');
    const hoursInput = document.getElementById('hours');
    const courseInfo = document.getElementById('courseHoursInfo');
    const loadInfo = document.getElementById('loadInfo');
    const search = document.getElementById('courseSearch');

    function updateCourseInfo() {
        const option = course.selectedOptions[0];
        if (!option || !option.value) {
            courseInfo.textContent = 'Choose a course to see the available hours.';
            courseInfo.className = 'info-box h-100 d-flex align-items-center';
            hoursInput.removeAttribute('max');
            return;
        }
        const key = type.value.toLowerCase();
        const planned = parseFloat(option.dataset[key] || '0');
        const assigned = parseFloat(option.dataset['assigned' + key.charAt(0).toUpperCase() + key.slice(1)] || '0');
        const available = Math.max(0, planned - assigned);

        if (planned === 0) {
            courseInfo.textContent = `This course plans no ${type.value} hours.`;
            courseInfo.className = 'info-box danger h-100 d-flex align-items-center';
            hoursInput.max = 0;
        } else {
            courseInfo.textContent = `${type.value}: ${formatHours(planned)} planned, ${formatHours(assigned)} already assigned, `
                + `${formatHours(available)} available.`;
            courseInfo.className = 'info-box h-100 d-flex align-items-center' + (available === 0 ? ' warning' : '');
            hoursInput.max = available;
        }
        updateLoad();
    }

    let loadRequest = 0;
    async function updateLoad() {
        const teacherId = teacher.value;
        const hours = parseFloat(hoursInput.value || '0');
        if (!teacherId) {
            loadInfo.hidden = true;
            return;
        }
        const requestId = ++loadRequest;
        const params = new URLSearchParams({ teacher_id: teacherId, hours: String(hours), exclude: form.dataset.exclude || '0' });
        try {
            const response = await fetch(`${form.dataset.apiLoad}?${params}`, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('HTTP ' + response.status);
            const data = await response.json();
            if (requestId !== loadRequest) return; // a more recent response is expected

            loadInfo.hidden = false;
            loadInfo.className = 'info-box' + (data.exceeds ? ' danger' : '');
            loadInfo.textContent = `Current workload: ${formatHours(data.teaching_load)} out of ${formatHours(data.max_load)}. `
                + `After this assignment: ${formatHours(data.after)}.`
                + (data.exceeds ? ' The maximum workload would be exceeded.' : '');
        } catch (error) {
            loadInfo.hidden = false;
            loadInfo.className = 'info-box warning';
            loadInfo.textContent = 'Cannot check the workload right now; it will be checked when saving.';
        }
    }

    let searchTimer;
    function filterCourses() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(async () => {
            const query = search.value.trim();
            const options = Array.from(course.options).slice(1);
            if (query === '') {
                options.forEach((option) => { option.hidden = false; });
                return;
            }
            try {
                const response = await fetch(`${form.dataset.apiSearch}?q=${encodeURIComponent(query)}`);
                const results = await response.json();
                const ids = new Set(results.map((item) => String(item.id)));
                options.forEach((option) => { option.hidden = !ids.has(option.value); });
                const firstVisible = options.find((option) => !option.hidden);
                if (firstVisible && course.selectedOptions[0]?.hidden) {
                    course.value = firstVisible.value;
                    updateCourseInfo();
                }
            } catch (error) {
                options.forEach((option) => { option.hidden = false; });
            }
        }, 250);
    }

    course.addEventListener('change', updateCourseInfo);
    type.addEventListener('change', updateCourseInfo);
    teacher.addEventListener('change', updateLoad);
    hoursInput.addEventListener('input', updateLoad);
    if (search) search.addEventListener('input', filterCourses);

    updateCourseInfo();
})();
