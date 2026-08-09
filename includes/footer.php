                </div> <!-- End Container -->
            </main> <!-- End app-content -->
            <footer class="site-footer" style="background:transparent;border-top:1px solid var(--border);margin:0 2rem;text-align:center;padding:.55rem 0;line-height:1.35;">
                <p style="margin:0;color:var(--text-muted);font-size:.75rem;">
                    © <?= date('Y') ?> ระบบประเมินสมรรถนะบุคลากรสาธารณสุข
                    <span style="white-space:nowrap;">• เวอร์ชัน 1.2.0</span>
                    <span>• พัฒนาโดย สำนักงานสาธารณสุขอำเภอบันนังสตา จังหวัดยะลา</span>
                </p>
            </footer>
        </div> <!-- End app-main -->
    </div> <!-- End app-layout -->
    
    <div class="toast-container" id="toastContainer"></div>
    
    <!-- Scripts -->
    <script src="<?= htmlspecialchars(appUrl('assets/js/script.js')) ?>?v=<?= time() ?>"></script>
</body>
</html>
