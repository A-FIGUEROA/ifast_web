<style>

        /* HEADER */
        .header {
            background: white;
            padding: 20px 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-left h1 {
            font-size: 1.8rem;
            color: #2c3e50;
            font-weight: 600;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #FDC500 0%, #FFD500 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .user-details {
            text-align: right;
        }

        .user-name {
            font-weight: 600;
            color: #2c3e50;
            font-size: 0.95rem;
        }

        .user-role {
            font-size: 0.8rem;
            color: #7f8c8d;
            display: inline-block;
            padding: 2px 8px;
            background: #ecf0f1;
            border-radius: 10px;
            margin-top: 2px;
        }

        .btn-logout {
            padding: 10px 20px;
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-logout:hover {
            background: #c0392b;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.3);
        }


            @media (max-width: 768px) {


            .header {
                padding: 15px;
            }

        }

</style>        
        
        <!-- MAIN CONTENT -->
        
            <!-- HEADER -->
            <header class="header">
                <div class="header-left">
                    <h1>Dashboard</h1>
                </div>
                <div class="header-right">
                    <div class="user-info">
                        <div class="user-avatar">
                            <?php echo substr($nombre_usuario, 0, 1); ?>
                        </div>
                        <div class="user-details">
                            <div class="user-name"><?php echo $nombre_usuario; ?></div>
                            <span class="user-role"><?php echo $tipo_usuario; ?></span>
                        </div>
                    </div>
                    <button class="btn-logout" onclick="location.href='logout.php'">
                        <box-icon name='log-in-circle' color='white'></box-icon> 
                    </button>
                </div>

                 <script src="https://unpkg.com/boxicons@2.1.4/dist/boxicons.js"></script>