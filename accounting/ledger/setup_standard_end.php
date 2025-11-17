        // Show confirmation
        const accountCount = selected.length;
        if (!confirm(`Are you sure you want to create ${accountCount} accounts? This action cannot be undone.`)) {
            e.preventDefault();
            return false;
        }
    });
    </script>
</body>
</html>