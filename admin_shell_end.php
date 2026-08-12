            </section>
        </div>
    </main>

    <div class="profile-modal" id="profileModal" hidden>
        <div class="profile-modal-backdrop" data-profile-close="backdrop"></div>
        <article class="profile-modal-card" role="dialog" aria-modal="true" aria-labelledby="profileModalTitle">
            <button class="profile-modal-close" type="button" id="profileModalClose" aria-label="Close profile preview">Close</button>

            <div class="profile-modal-head">
                <div class="profile-modal-avatar" id="profileModalAvatar"></div>
                <div>
                    <h3 id="profileModalTitle">Employee Profile</h3>
                    <p id="profileModalSubtitle">Attendance details</p>
                </div>
            </div>

            <dl class="profile-modal-metrics" id="profileModalMetrics"></dl>
            <div id="profileModalStatusWrap"></div>
        </article>
    </div>

    <script>
        const profileReviewData = <?= json_encode($profileReviewData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        const profileModal = document.getElementById('profileModal');
        const profileModalClose = document.getElementById('profileModalClose');
        const profileModalAvatar = document.getElementById('profileModalAvatar');
        const profileModalTitle = document.getElementById('profileModalTitle');
        const profileModalSubtitle = document.getElementById('profileModalSubtitle');
        const profileModalMetrics = document.getElementById('profileModalMetrics');
        const profileModalStatusWrap = document.getElementById('profileModalStatusWrap');

        function escapeHtml(value) {
            return String(value)
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
        }

        function profileInitials(name) {
            const parts = String(name).trim().split(/\s+/).filter(Boolean);

            if (parts.length === 0) {
                return 'NA';
            }

            if (parts.length === 1) {
                return parts[0].slice(0, 2).toUpperCase();
            }

            return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
        }

        function closeProfileModal() {
            if (profileModal) {
                profileModal.hidden = true;
                document.body.classList.remove('modal-open');
            }
        }

        function openProfileModal(index) {
            const profile = profileReviewData[index];

            if (!profile || !profileModal) {
                return;
            }

            const statusClass = profile.status === 'Complete' ? 'complete' : 'incomplete';
            const avatarHtml = profile.clock_in_photo !== ''
                ? `<img src="${escapeHtml(profile.clock_in_photo)}" alt="Clock-in photo for ${escapeHtml(profile.employee_number)}">`
                : `<span>${escapeHtml(profileInitials(profile.employee_name))}</span>`;

            profileModalAvatar.innerHTML = avatarHtml;
            profileModalTitle.textContent = profile.employee_name;
            profileModalSubtitle.textContent = `${profile.employee_number} - ${profile.department_name}`;

            profileModalMetrics.innerHTML = `
                <div><dt>Position</dt><dd>${escapeHtml(profile.position || '-')}</dd></div>
                <div><dt>Clock In</dt><dd>${escapeHtml(profile.clock_in || '-')}</dd></div>
                <div><dt>Clock Out</dt><dd>${escapeHtml(profile.clock_out || '-')}</dd></div>
                <div><dt>Worked Time</dt><dd>${escapeHtml(profile.worked_hours || '-')}</dd></div>
            `;

            profileModalStatusWrap.innerHTML = `<span class="badge ${statusClass}">${escapeHtml(profile.status)}</span>`;
            profileModal.hidden = false;
            document.body.classList.add('modal-open');
        }

        document.querySelectorAll('.profile-name-button').forEach((button) => {
            button.addEventListener('click', () => {
                openProfileModal(Number(button.dataset.profileIndex));
            });
        });

        profileModalClose?.addEventListener('click', closeProfileModal);
        profileModal?.addEventListener('click', (event) => {
            if (event.target instanceof HTMLElement && event.target.dataset.profileClose === 'backdrop') {
                closeProfileModal();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && profileModal && !profileModal.hidden) {
                closeProfileModal();
            }
        });

        const recordSearch = document.getElementById('recordSearch');
        const recordStatusFilter = document.getElementById('recordStatusFilter');
        const recordsFilterSummary = document.getElementById('recordsFilterSummary');
        const recordsFilterEmpty = document.getElementById('recordsFilterEmpty');
        const recordRows = Array.from(document.querySelectorAll('[data-record-row]'));
        const employeeSearch = document.getElementById('employeeSearch');
        const employeeFilterSummary = document.getElementById('employeeFilterSummary');
        const employeeFilterEmpty = document.getElementById('employeeFilterEmpty');
        const employeeRows = Array.from(document.querySelectorAll('[data-employee-row]'));
        const geofenceLatitudeInput = document.getElementById('geofence_latitude');
        const geofenceLongitudeInput = document.getElementById('geofence_longitude');
        const useCurrentGeofenceLocationButton = document.getElementById('useCurrentGeofenceLocation');
        const geofenceAutoStatus = document.getElementById('geofenceAutoStatus');

        function applyRecordsFilter() {
            if (recordRows.length === 0) {
                return;
            }

            const searchValue = (recordSearch?.value || '').trim().toLowerCase();
            const statusValue = (recordStatusFilter?.value || 'all').toLowerCase();
            let visibleCount = 0;

            recordRows.forEach((row) => {
                const haystack = String(row.getAttribute('data-record-search') || '');
                const status = String(row.getAttribute('data-record-status') || 'incomplete');
                const searchMatch = searchValue === '' || haystack.includes(searchValue);
                const statusMatch = statusValue === 'all' || status === statusValue;
                const isVisible = searchMatch && statusMatch;

                row.hidden = !isVisible;

                if (isVisible) {
                    visibleCount += 1;
                }
            });

            if (recordsFilterSummary) {
                recordsFilterSummary.textContent = `${visibleCount} of ${recordRows.length} records shown`;
            }

            if (recordsFilterEmpty) {
                recordsFilterEmpty.hidden = visibleCount !== 0;
            }
        }

        recordSearch?.addEventListener('input', applyRecordsFilter);
        recordStatusFilter?.addEventListener('change', applyRecordsFilter);
        applyRecordsFilter();

        function applyEmployeeFilter() {
            if (employeeRows.length === 0) {
                return;
            }

            const searchValue = (employeeSearch?.value || '').trim().toLowerCase();
            let visibleCount = 0;

            employeeRows.forEach((row) => {
                const haystack = String(row.getAttribute('data-employee-search') || '');
                const isVisible = searchValue === '' || haystack.includes(searchValue);

                row.hidden = !isVisible;

                if (isVisible) {
                    visibleCount += 1;
                }
            });

            if (employeeFilterSummary) {
                employeeFilterSummary.textContent = `${visibleCount} of ${employeeRows.length} staff shown`;
            }

            if (employeeFilterEmpty) {
                employeeFilterEmpty.hidden = visibleCount !== 0;
            }
        }

        employeeSearch?.addEventListener('input', applyEmployeeFilter);
        applyEmployeeFilter();

        useCurrentGeofenceLocationButton?.addEventListener('click', () => {
            if (!('geolocation' in navigator)) {
                if (geofenceAutoStatus) {
                    geofenceAutoStatus.textContent = 'Geolocation is not supported on this device.';
                }
                return;
            }

            if (window.isSecureContext === false) {
                if (geofenceAutoStatus) {
                    geofenceAutoStatus.textContent = 'Location access requires HTTPS.';
                }
                return;
            }

            useCurrentGeofenceLocationButton.disabled = true;

            if (geofenceAutoStatus) {
                geofenceAutoStatus.textContent = 'Getting your current location...';
            }

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const latitude = Number(position.coords.latitude).toFixed(6);
                    const longitude = Number(position.coords.longitude).toFixed(6);

                    if (geofenceLatitudeInput) {
                        geofenceLatitudeInput.value = latitude;
                    }

                    if (geofenceLongitudeInput) {
                        geofenceLongitudeInput.value = longitude;
                    }

                    if (geofenceAutoStatus) {
                        geofenceAutoStatus.textContent = `Location updated (accuracy ${Math.round(position.coords.accuracy)}m).`;
                    }

                    useCurrentGeofenceLocationButton.disabled = false;
                },
                () => {
                    if (geofenceAutoStatus) {
                        geofenceAutoStatus.textContent = 'Unable to read location. Allow permission and try again.';
                    }

                    useCurrentGeofenceLocationButton.disabled = false;
                },
                {
                    enableHighAccuracy: true,
                    timeout: 12000,
                    maximumAge: 0
                }
            );
        });

        document.querySelectorAll('[data-print-mode]').forEach((button) => {
            button.addEventListener('click', () => {
                document.body.dataset.printMode = button.dataset.printMode;
                window.print();
            });
        });

        window.addEventListener('afterprint', () => {
            delete document.body.dataset.printMode;
        });
    </script>
</body>
</html>
