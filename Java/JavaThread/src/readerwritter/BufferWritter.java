package readerwritter;

import java.util.Random;

public class BufferWritter implements Runnable {
	private Buffer buffer;
	public BufferWritter(Buffer buffer){
		this.buffer=buffer;
	}
	public void write(int num) throws InterruptedException{
		buffer.write(num);
	}
	@Override
	public void run() {
		while(true){
			try {
				int num=new Random().nextInt(20000);
				write(num);
				System.out.println("Write: "+num);
			} catch (InterruptedException e) {
				// TODO Auto-generated catch block
				e.printStackTrace();
			}
		}
		
	}
}
