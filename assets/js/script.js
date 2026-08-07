/**
 * Smart Evaluate v2.0 — Modern UI Interactions
 * Handles: navbar scroll, hamburger menu, scroll animations, toast system
 */
document.addEventListener('DOMContentLoaded', function() {

    // ============================================
    // HAMBURGER MENU TOGGLE (Mobile)
    // ============================================
    const hamburger = document.getElementById('hamburgerBtn');
    const globalSidebar = document.getElementById('globalSidebar');
    
    if (hamburger && globalSidebar) {
        hamburger.addEventListener('click', () => {
            hamburger.classList.toggle('active');
            globalSidebar.classList.toggle('show');
        });

        // Close menu when clicking a link (on mobile)
        globalSidebar.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    hamburger.classList.remove('active');
                    globalSidebar.classList.remove('show');
                }
            });
        });

        // Close menu when clicking outside
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 768 && !hamburger.contains(e.target) && !globalSidebar.contains(e.target)) {
                hamburger.classList.remove('active');
                globalSidebar.classList.remove('show');
            }
        });
    }

    // ============================================
    // SCROLL ANIMATIONS (Intersection Observer)
    // ============================================
    const animateElements = document.querySelectorAll('.animate-on-scroll');
    if (animateElements.length > 0 && 'IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    observer.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '0px 0px -40px 0px'
        });

        animateElements.forEach(el => observer.observe(el));
    }

    // ============================================
    // ASSESSMENT TABS & NAVIGATION
    // ============================================
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabPanes = document.querySelectorAll('.tab-pane');
    const navPrevBtns = document.querySelectorAll('.nav-prev');
    const navNextBtns = document.querySelectorAll('.nav-next');
    
    function switchTab(targetId) {
        try {
            document.querySelectorAll('.tab-btn, .tab-btn-vertical').forEach(btn => btn.classList.remove('active'));
            tabPanes.forEach(pane => pane.classList.remove('active'));
            
            const targetBtns = document.querySelectorAll(`.tab-btn[data-target="${targetId}"], .tab-btn-vertical[data-target="${targetId}"]`);
            const targetPane = document.getElementById(targetId);
            
            targetBtns.forEach(btn => btn.classList.add('active'));
            if (targetPane) targetPane.classList.add('active');
            
            if (targetId === 'tab-summary') {
                calculateSummary();
            }
            
            window.scrollTo(0, 0);
        } catch (e) {
            console.error('Error in switchTab:', e);
            alert('เกิดข้อผิดพลาดในการเปลี่ยนหน้า: ' + e.message);
        }
    }
    
    // Also update tab listeners for both classes
    document.querySelectorAll('.tab-btn, .tab-btn-vertical').forEach(btn => {
        btn.addEventListener('click', (e) => { e.preventDefault(); switchTab(btn.dataset.target); });
    });
    
    navPrevBtns.forEach(btn => {
        btn.addEventListener('click', (e) => { e.preventDefault(); switchTab(btn.dataset.target); });
    });
    
    navNextBtns.forEach(btn => {
        btn.addEventListener('click', (e) => { e.preventDefault(); switchTab(btn.dataset.target); });
    });

    // ============================================
    // RADIO SCORE & REASON LOGIC (Matrix)
    // ============================================
    const allRadios = document.querySelectorAll('.matrix-radio');
    
    allRadios.forEach(radio => {
        radio.addEventListener('change', (e) => {
            const container = e.target.closest('.question-item');
            const val = parseInt(e.target.value);
            
            // Highlight the answered row
            container.classList.add('answered');
            
            updateProgress();
        });
    });
    
    function updateProgress() {
        const compStats = {};
        let totalSystemQuestions = 0;
        
        document.querySelectorAll('.question-item').forEach(item => {
            const compId = item.dataset.compId;
            if (!compStats[compId]) {
                compStats[compId] = { total: 0, answered: 0 };
            }
            compStats[compId].total++;
            totalSystemQuestions++;
        });

        const answeredQuestions = new Set();
        document.querySelectorAll('.matrix-radio:checked').forEach(radio => {
            const container = radio.closest('.question-item');
            const compId = container.dataset.compId;
            answeredQuestions.add(container.dataset.indicatorId);
            if (compStats[compId]) {
                compStats[compId].answered++;
            }
        });
        const answered = answeredQuestions.size;
        const progressBar = document.getElementById('assessmentProgress');
        const progressText = document.getElementById('progressText');
        
        if (progressBar && progressText) {
            const percent = totalSystemQuestions > 0 ? (answered / totalSystemQuestions) * 100 : 0;
            progressBar.style.width = `${percent}%`;
            progressText.textContent = `ประเมินแล้ว ${answered} / ${totalSystemQuestions} ข้อ`;
        }

        // Update badges
        Object.keys(compStats).forEach(compId => {
            const badge = document.getElementById(`badge-tab-${compId}`);
            if (badge) {
                const stat = compStats[compId];
                if (stat.answered === stat.total && stat.total > 0) {
                    badge.textContent = '';
                    badge.classList.add('is-complete');
                    badge.style.backgroundColor = 'transparent';
                } else {
                    badge.classList.remove('is-complete');
                    badge.innerHTML = `${stat.answered}/${stat.total}`;
                }
            }
        });
    }
    
    // Initial progress update
    if (document.getElementById('assessmentForm')) {
        updateProgress();
    }

    // ============================================
    // CALCULATION LOGIC
    // ============================================
    function calculateSummary() {
        const tbody = document.querySelector('#summaryTable tbody');
        if (!tbody) return;
        
        tbody.innerHTML = '';
        
        // Group answers by competency
        const comps = {};
        const compOrder = [];
        document.querySelectorAll('.question-item').forEach(item => {
            const compId = item.dataset.compId;
            const compName = item.closest('.card').querySelector('.card-title').textContent
                .trim()
                .replace(/\s*\([^)]*\)\s*$/, '');
            const weight = parseFloat(item.dataset.weight);
            const type = item.dataset.compType || 'core';
            const expectedLevel = item.dataset.expectedLevel || 1;
            const checked = item.querySelector('.matrix-radio:checked');
            
            if (!comps[compId]) {
                comps[compId] = { name: compName, weight: weight, type: type, expectedLevel: expectedLevel, scores: [], sum: 0 };
                compOrder.push(compId);
            }
            
            if (checked) {
                const val = parseInt(checked.value);
                comps[compId].scores.push(val);
                comps[compId].sum += val;
            }
        });
        
        let totalBase5 = 0;
        
        // Group by type for rendering
        // ห้ามใช้ Object.values โดยตรง เพราะ key ที่เป็นตัวเลขจะถูกเรียงตาม id
        // และทำให้ลำดับสมรรถนะเฉพาะไม่ตรงกับแบบของแต่ละตำแหน่ง
        const orderedComps = compOrder.map(compId => comps[compId]);
        const coreComps = orderedComps.filter(c => c.type === 'core');
        const funcComps = orderedComps.filter(c => c.type === 'functional');

        const escapeHtml = (value) => String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
        const formatWeight = (weight) => Number.isInteger(weight) ? weight.toFixed(0) : weight.toFixed(2);
        
        if (coreComps.length > 0) {
            const tr = document.createElement('tr');
            tr.className = 'summary-section-row';
            tr.innerHTML = `<td class="summary-section-title">สมรรถนะหลัก</td><td></td><td></td><td></td><td></td>`;
            tbody.appendChild(tr);
            
            coreComps.forEach((comp, index) => {
                const count = comp.scores.length;
                const rawAvg = count > 0 ? (comp.sum / count) : 0;
                // ให้ตรงกับ Excel: ปัดค่าเฉลี่ย (ก) เป็น 2 ตำแหน่งก่อนคูณน้ำหนัก
                const avg = Math.round((rawAvg + Number.EPSILON) * 100) / 100;
                const weighted = avg * (comp.weight / 100);
                totalBase5 += weighted;
                
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="summary-name">${index + 1}. ${escapeHtml(comp.name)}</td>
                    <td class="summary-number">${escapeHtml(comp.expectedLevel)}</td>
                    <td class="summary-number">${count > 0 ? avg.toFixed(2) : ''}</td>
                    <td class="summary-number">${formatWeight(comp.weight)}%</td>
                    <td class="summary-number">${count > 0 ? weighted.toFixed(1) : '0.0'}</td>
                `;
                tbody.appendChild(tr);
            });
        }
        
        if (funcComps.length > 0) {
            const tr = document.createElement('tr');
            tr.className = 'summary-section-row';
            tr.innerHTML = `<td class="summary-section-title">สมรรถนะเฉพาะตามลักษณะงานที่ปฏิบัติ</td><td></td><td></td><td></td><td></td>`;
            tbody.appendChild(tr);
            
            const offset = coreComps.length;
            funcComps.forEach((comp, index) => {
                const count = comp.scores.length;
                const rawAvg = count > 0 ? (comp.sum / count) : 0;
                // ให้ตรงกับ Excel: ปัดค่าเฉลี่ย (ก) เป็น 2 ตำแหน่งก่อนคูณน้ำหนัก
                const avg = Math.round((rawAvg + Number.EPSILON) * 100) / 100;
                const weighted = avg * (comp.weight / 100);
                totalBase5 += weighted;
                
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="summary-name">${offset + index + 1}. ${escapeHtml(comp.name)}</td>
                    <td class="summary-number">${escapeHtml(comp.expectedLevel)}</td>
                    <td class="summary-number">${count > 0 ? avg.toFixed(2) : ''}</td>
                    <td class="summary-number">${formatWeight(comp.weight)}%</td>
                    <td class="summary-number">${count > 0 ? weighted.toFixed(1) : '0.0'}</td>
                `;
                tbody.appendChild(tr);
            });
        }
        
        const totalBase100 = Math.round((totalBase5 * 20 + Number.EPSILON) * 100) / 100;
        
        const totalBase5El = document.getElementById('totalBase5');
        const totalBase100El = document.getElementById('totalBase100');
        
        if (totalBase5El) totalBase5El.textContent = totalBase5 === 0 ? '0.0' : totalBase5.toFixed(1);
        if (totalBase100El) totalBase100El.textContent = totalBase100 === 0 ? '0.00' : totalBase100.toFixed(2);
    }

    // assessment.php มีปุ่มนำทางด้านล่างที่เรียก switchTab จาก inline script
    // จึงต้องเปิดฟังก์ชันคำนวณให้เรียกใช้ได้จากทั้งปุ่มแท็บและปุ่ม "ดูสรุปคะแนน"
    window.calculateSummary = calculateSummary;

    // ============================================
    // FORM VALIDATION
    // ============================================
    const form = document.getElementById('assessmentForm');
    if (form) {
        form.addEventListener('submit', (e) => {
            const action = e.submitter ? e.submitter.value : 'draft';
            
            if (action === 'submit') {
                const totalQuestions = document.querySelectorAll('.question-item').length;
                const answered = document.querySelectorAll('.matrix-radio:checked').length;
                
                if (answered < totalQuestions) {
                    e.preventDefault();
                    alert(`กรุณาประเมินให้ครบทุกข้อ (ประเมินแล้ว ${answered}/${totalQuestions} ข้อ)`);
                    return;
                }
                

                
                if (!confirm('คุณยืนยันที่จะส่งผลการประเมินใช่หรือไม่? (หากส่งแล้วจะไม่สามารถแก้ไขได้)')) {
                    e.preventDefault();
                }
            }
        });
    }

});
