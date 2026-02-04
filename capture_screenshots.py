import os
from playwright.sync_api import sync_playwright

def generate_screenshots():
    cwd = os.getcwd()
    search_path = f"file://{cwd}/mock_search.html"
    single_path = f"file://{cwd}/mock_single.html"

    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page(viewport={'width': 1280, 'height': 800}) # Desktop size

        # Screenshot 1: Search
        print(f"Navigating to {search_path}")
        page.goto(search_path)
        # Wait for fonts potentially, though likely fast
        page.screenshot(path="assets-wp-repo/screenshot-1.png", full_page=True)
        print("Created screenshot-1.png")

        # Screenshot 2: Single
        print(f"Navigating to {single_path}")
        page.goto(single_path)
        page.screenshot(path="assets-wp-repo/screenshot-2.png", full_page=True)
        print("Created screenshot-2.png")

        browser.close()

if __name__ == "__main__":
    generate_screenshots()
