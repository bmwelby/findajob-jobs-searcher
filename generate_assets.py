from PIL import Image, ImageDraw, ImageFont
import os

def create_image(filename, size, text, bg_color="#0073aa", text_color="#ffffff", font_size=40):
    img = Image.new('RGB', size, color=bg_color)
    d = ImageDraw.Draw(img)

    try:
        font = ImageFont.truetype("/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf", font_size)
    except IOError:
        font = ImageFont.load_default()
        print("Warning: Could not load custom font, using default.")

    # Calculate text position to center it
    # getbbox returns (left, top, right, bottom)
    bbox = d.textbbox((0, 0), text, font=font)
    text_width = bbox[2] - bbox[0]
    text_height = bbox[3] - bbox[1]

    # Adjust for ascent/descent
    # textbbox is good, but let's try to center visually
    x = (size[0] - text_width) / 2
    y = (size[1] - text_height) / 2 - bbox[1] # Subtract top to align properly

    d.text((x, y), text, fill=text_color, font=font)
    img.save(filename)
    print(f"Created {filename}")

# Create assets directory if not exists
if not os.path.exists('assets-wp-repo'):
    os.makedirs('assets-wp-repo')

# Icons
create_image('assets-wp-repo/icon-256x256.png', (256, 256), "FJS", font_size=100)
img = Image.open('assets-wp-repo/icon-256x256.png')
img.resize((128, 128)).save('assets-wp-repo/icon-128x128.png')
print("Created assets-wp-repo/icon-128x128.png")

# Banners
create_image('assets-wp-repo/banner-1544x500.png', (1544, 500), "Find a Job Searcher", font_size=100)
img = Image.open('assets-wp-repo/banner-1544x500.png')
img.resize((772, 250)).save('assets-wp-repo/banner-772x250.png')
print("Created assets-wp-repo/banner-772x250.png")
