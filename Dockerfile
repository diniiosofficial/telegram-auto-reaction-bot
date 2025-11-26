# Use official PHP runtime
FROM php:8.2-alpine

# Set working directory
WORKDIR /app

# Copy all files to container
COPY . .

# Install any required PHP extensions if needed
# RUN docker-php-ext-install mysqli pdo pdo_mysql

# Expose port
EXPOSE 8000

# Start PHP built-in server
CMD ["php", "-S", "0.0.0.0:8000"]
